<?php

declare(strict_types=1);

use Authn\Sdk\Tests\Support\JwtFixture;
use Authn\Sdk\Tests\Support\MemoryCache;
use Authn\Sdk\Tests\Support\StaticBodyClient;
use Authn\Sdk\Tokens\TokenInvalidException;
use Authn\Sdk\Tokens\TokenVerifier;

function makeVerifier(
    StaticBodyClient $http,
    ?MemoryCache $cache = null,
    int $skew = 5,
    int $ttl = 600,
): TokenVerifier {
    return new TokenVerifier(
        publishableKey: 'pk_test_dummy',
        frontendApiUrl: 'https://acme.authn.sh',
        cache: $cache ?? new MemoryCache,
        http: $http,
        jwksCacheTtlSeconds: $ttl,
        allowedClockSkewSeconds: $skew,
    );
}

/**
 * @return array<string, mixed>
 */
function validClaims(): array
{
    $now = time();

    return [
        'iss' => 'https://acme.authn.sh',
        'sub' => 'user_2x',
        'sid' => 'sess_1y',
        'azp' => 'https://app.example',
        'iat' => $now - 1,
        'exp' => $now + 3600,
        'nbf' => $now - 5,
        'was_test' => true,
    ];
}

it('verifies a well-formed JWT and returns parsed claims', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));
    $jwt = $fixture->sign(validClaims());

    $claims = $verifier->verify($jwt);

    expect($claims->sub)->toBe('user_2x');
    expect($claims->sid)->toBe('sess_1y');
    expect($claims->iss)->toBe('https://acme.authn.sh');
    expect($claims->azp)->toBe('https://app.example');
    expect($claims->wasTest)->toBeTrue();
    expect($claims->raw['was_test'])->toBeTrue();
});

it('caches the JWKS for the configured TTL (one fetch across many verifies)', function (): void {
    $fixture = new JwtFixture;
    $http = new StaticBodyClient($fixture->jwksJson());
    $verifier = makeVerifier($http, new MemoryCache);

    for ($i = 0; $i < 5; $i++) {
        $verifier->verify($fixture->sign(validClaims()));
    }

    expect($http->calls)->toBe(1);
});

it('refreshes JWKS once on unknown kid; success when the new doc carries it', function (): void {
    $oldFixture = new JwtFixture('old-kid');
    $newFixture = new JwtFixture('new-kid');

    $http = new StaticBodyClient($oldFixture->jwksJson(), $newFixture->jwksJson());
    $cache = new MemoryCache;
    $verifier = makeVerifier($http, $cache);

    $verifier->verify($oldFixture->sign(validClaims()));
    expect($http->calls)->toBe(1);

    $jwtFromNew = $newFixture->sign(validClaims());
    $claims = $verifier->verify($jwtFromNew);

    expect($claims->sub)->toBe('user_2x');
    expect($http->calls)->toBe(2);
});

it('throws TokenInvalidException when kid stays unknown after refresh', function (): void {
    $serverFixture = new JwtFixture('server-kid');
    $strangerFixture = new JwtFixture('stranger-kid');

    $cache = new MemoryCache;
    $http = new StaticBodyClient($serverFixture->jwksJson(), $serverFixture->jwksJson());
    $verifier = makeVerifier($http, $cache);

    // Warm the cache with a successful verify, so the next call sees a cache hit.
    $verifier->verify($serverFixture->sign(validClaims()));
    expect($http->calls)->toBe(1);

    // Now an unknown kid must trigger exactly one refresh (call #2) before failing.
    expect(fn () => $verifier->verify($strangerFixture->sign(validClaims())))
        ->toThrow(TokenInvalidException::class);
    expect($http->calls)->toBe(2);
});

it('rejects an expired JWT', function (): void {
    $fixture = new JwtFixture;
    $http = new StaticBodyClient($fixture->jwksJson());
    $verifier = makeVerifier($http);

    $claims = validClaims();
    $claims['exp'] = time() - 60;
    $claims['iat'] = time() - 120;

    expect(fn () => $verifier->verify($fixture->sign($claims)))->toThrow(TokenInvalidException::class);
});

it('rejects a JWT whose iss does not match the FAPI URL', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['iss'] = 'https://impostor.authn.sh';

    expect(fn () => $verifier->verify($fixture->sign($claims)))->toThrow(TokenInvalidException::class);
});

it('rejects a JWT with a bad signature', function (): void {
    $serverFixture = new JwtFixture;
    $attackerFixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($serverFixture->jwksJson()));

    $jwt = $attackerFixture->sign(validClaims(), headerOverrides: ['kid' => $serverFixture->kid]);

    expect(fn () => $verifier->verify($jwt))->toThrow(TokenInvalidException::class);
});

it('enforces expectedAzp when the caller passes it', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));
    $jwt = $fixture->sign(validClaims());

    expect($verifier->verify($jwt, ['https://app.example']))->not->toBeNull();
    expect(fn () => $verifier->verify($jwt, ['https://wrong.example']))
        ->toThrow(TokenInvalidException::class);
});

it('tryVerify returns null on failure instead of throwing', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    expect($verifier->tryVerify('not.a.jwt'))->toBeNull();
});

it('rejects malformed JWTs with TokenInvalidException', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    expect(fn () => $verifier->verify('definitely.not.a.jwt'))->toThrow(TokenInvalidException::class);
});

it('parses actor and org claims when present', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['act'] = ['iss' => 'https://acme.authn.sh', 'sub' => 'user_admin', 'sid' => 'sess_admin'];
    $claims['org'] = ['id' => 'org_1', 'slg' => 'acme', 'rol' => 'admin', 'per' => ['users:read', 'users:write']];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->actor)->toBe(['iss' => 'https://acme.authn.sh', 'sub' => 'user_admin', 'sid' => 'sess_admin']);
    $org = $verified->org;
    expect($org)->not->toBeNull();
    expect($org)->toMatchArray(['id' => 'org_1', 'slg' => 'acme', 'rol' => 'admin']);
    expect($org['per'] ?? null)->toBe(['users:read', 'users:write']);
});
