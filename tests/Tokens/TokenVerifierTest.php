<?php

declare(strict_types=1);

use Authn\Sdk\Tests\Support\JwtFixture;
use Authn\Sdk\Tests\Support\MemoryCache;
use Authn\Sdk\Tests\Support\StaticBodyClient;
use Authn\Sdk\Tokens\Organization;
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

    // deprecated array alias still works
    $org = $verified->org;
    expect($org)->not->toBeNull();
    expect($org)->toMatchArray(['id' => 'org_1', 'slg' => 'acme', 'rol' => 'admin']);
    expect($org['per'] ?? null)->toBe(['users:read', 'users:write']);

    // typed value object
    expect($verified->organization)->toBeInstanceOf(Organization::class);
    expect($verified->organization?->id)->toBe('org_1');
    expect($verified->organization?->slug)->toBe('acme');
    expect($verified->organization?->role)->toBe('admin');
    expect($verified->organization?->permissions)->toBe(['users:read', 'users:write']);
});

it('populates organization from the JWT org claim with correct fields', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['org'] = [
        'id' => 'org_2',
        'slg' => 'beta-corp',
        'rol' => 'org:member',
        'per' => ['org:sys_profile:read', 'org:sys_memberships:read'],
    ];

    $verified = $verifier->verify($fixture->sign($claims));
    $org = $verified->organization;

    expect($org)->toBeInstanceOf(Organization::class);
    expect($org?->id)->toBe('org_2');
    expect($org?->slug)->toBe('beta-corp');
    expect($org?->role)->toBe('org:member');
    expect($org?->permissions)->toBe(['org:sys_profile:read', 'org:sys_memberships:read']);

    expect($verified->hasRole('org:member'))->toBeTrue();
    expect($verified->hasRole('org:admin'))->toBeFalse();
    expect($verified->hasPermission('org:sys_profile:read'))->toBeTrue();
    expect($verified->hasPermission('org:sys_profile:manage'))->toBeFalse();
});

it('organization is null and hasRole/hasPermission return false when org claim absent', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->organization)->toBeNull();
    expect($verified->hasRole('admin'))->toBeFalse();
    expect($verified->hasPermission('org:sys_profile:manage'))->toBeFalse();
});

it('permissions array is deduped while preserving order', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['org'] = [
        'id' => 'org_3',
        'per' => ['perm:a', 'perm:b', 'perm:a', 'perm:c'],
    ];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->organization?->permissions)->toBe(['perm:a', 'perm:b', 'perm:c']);
});

it('surfaces fva second-factor age and twoFactorVerified when second factor is present', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['fva'] = [30, 120];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->twoFactorVerified)->toBeTrue();
    expect($verified->firstFactorAgeSeconds)->toBe(30);
    expect($verified->secondFactorAgeSeconds)->toBe(120);
});

it('surfaces fva with second-factor age of -1 as twoFactorVerified=false and null age', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['fva'] = [45, -1];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->twoFactorVerified)->toBeFalse();
    expect($verified->firstFactorAgeSeconds)->toBe(45);
    expect($verified->secondFactorAgeSeconds)->toBeNull();
});

it('defaults fva fields to false/null when fva claim is absent', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->twoFactorVerified)->toBeFalse();
    expect($verified->firstFactorAgeSeconds)->toBeNull();
    expect($verified->secondFactorAgeSeconds)->toBeNull();
});

it('maps first-factor-age of -1 to null firstFactorAgeSeconds', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['fva'] = [-1, -1];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->twoFactorVerified)->toBeFalse();
    expect($verified->firstFactorAgeSeconds)->toBeNull();
    expect($verified->secondFactorAgeSeconds)->toBeNull();
});

it('surfaces phoneNumberVerified when pnv claim is true', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pnv'] = true;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->phoneNumberVerified)->toBeTrue();
    expect($verified->hasVerifiedPhoneNumber())->toBeTrue();
});

it('defaults phoneNumberVerified to false when pnv claim is absent', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->phoneNumberVerified)->toBeFalse();
    expect($verified->hasVerifiedPhoneNumber())->toBeFalse();
});

it('treats pnv: false as phoneNumberVerified=false', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pnv'] = false;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->phoneNumberVerified)->toBeFalse();
});

it('parses dsf as defaultSecondFactor for each supported factor', function (string $factor): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['dsf'] = $factor;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->defaultSecondFactor)->toBe($factor);
    expect($verified->preferredSecondFactor())->toBe($factor);
})->with(['totp', 'phone_code', 'backup_code']);

it('defaults defaultSecondFactor to null when dsf claim is absent', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->defaultSecondFactor)->toBeNull();
    expect($verified->preferredSecondFactor())->toBeNull();
});

it('rejects unknown dsf values as null defaultSecondFactor', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['dsf'] = 'webauthn';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->defaultSecondFactor)->toBeNull();
});

it('surfaces passkeyVerified=true when pkv claim is true', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pkv'] = true;
    $claims['pkc'] = 2;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->passkeyVerified)->toBeTrue();
    expect($verified->wasVerifiedByPasskey())->toBeTrue();
    expect($verified->passkeyCount)->toBe(2);
    expect($verified->hasPasskey())->toBeTrue();
});

it('defaults passkey claims when pkv and pkc are absent (non-passkey session)', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->passkeyVerified)->toBeFalse();
    expect($verified->wasVerifiedByPasskey())->toBeFalse();
    expect($verified->passkeyCount)->toBe(0);
    expect($verified->hasPasskey())->toBeFalse();
});

it('treats pkv: false as passkeyVerified=false', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pkv'] = false;
    $claims['pkc'] = 0;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->passkeyVerified)->toBeFalse();
    expect($verified->passkeyCount)->toBe(0);
    expect($verified->hasPasskey())->toBeFalse();
});

it('exposes both passkeyVerified and twoFactorVerified for passkey + TOTP step-up sessions', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pkv'] = true;
    $claims['pkc'] = 1;
    $claims['fva'] = [30, 120];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->passkeyVerified)->toBeTrue();
    expect($verified->twoFactorVerified)->toBeTrue();
    expect($verified->firstFactorAgeSeconds)->toBe(30);
    expect($verified->secondFactorAgeSeconds)->toBe(120);
});

it('ignores non-integer and negative pkc values, defaulting passkeyCount to 0', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['pkc'] = '3';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->passkeyCount)->toBe(0);

    $claims2 = validClaims();
    $claims2['pkc'] = -1;
    $verified2 = $verifier->verify($fixture->sign($claims2));

    expect($verified2->passkeyCount)->toBe(0);
});

it('surfaces enterprise SSO claims when entcon and entacc are present', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['entcon'] = 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA';
    $claims['entacc'] = 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->enterpriseConnectionId)->toBe('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($verified->enterpriseAccountId)->toBe('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB');
    expect($verified->wasVerifiedByEnterpriseSso())->toBeTrue();
});

it('defaults entcon and entacc to null on a non-enterprise session', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->enterpriseConnectionId)->toBeNull();
    expect($verified->enterpriseAccountId)->toBeNull();
    expect($verified->wasVerifiedByEnterpriseSso())->toBeFalse();
});

it('ignores non-string entcon and entacc values, defaulting to null', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['entcon'] = 123;
    $claims['entacc'] = ['nested' => 'value'];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->enterpriseConnectionId)->toBeNull();
    expect($verified->enterpriseAccountId)->toBeNull();
    expect($verified->wasVerifiedByEnterpriseSso())->toBeFalse();
});

it('treats empty-string entcon and entacc as absent', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['entcon'] = '';
    $claims['entacc'] = '';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->enterpriseConnectionId)->toBeNull();
    expect($verified->enterpriseAccountId)->toBeNull();
    expect($verified->wasVerifiedByEnterpriseSso())->toBeFalse();
});

it('preserves entcon and entacc across impersonation (actor) sessions', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['entcon'] = 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA';
    $claims['entacc'] = 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB';
    $claims['act'] = [
        'iss' => $claims['iss'],
        'sub' => 'user_admin',
        'sid' => 'sess_admin',
    ];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->actor)->not->toBeNull();
    expect($verified->enterpriseConnectionId)->toBe('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($verified->enterpriseAccountId)->toBe('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZB');
});

it('surfaces custom-template claims as customClaims', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['email'] = 'jane@acme.example';
    $claims['role'] = 'authenticated';
    $claims['org_slug'] = 'acme';
    $claims['tier'] = 'pro';
    $claims['metadata'] = ['plan' => 'team', 'seats' => 25];

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->customClaims)->toBe([
        'email' => 'jane@acme.example',
        'role' => 'authenticated',
        'org_slug' => 'acme',
        'tier' => 'pro',
        'metadata' => ['plan' => 'team', 'seats' => 25],
    ]);
    expect($verified->customClaim('tier'))->toBe('pro');
    expect($verified->customClaim('metadata'))->toBe(['plan' => 'team', 'seats' => 25]);
});

it('excludes every reserved claim from customClaims', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['org'] = ['id' => 'org_1', 'slg' => 'acme'];
    $claims['fva'] = [10, 20];
    $claims['pnv'] = true;
    $claims['dsf'] = 'totp';
    $claims['pkv'] = true;
    $claims['pkc'] = 1;
    $claims['entcon'] = 'entcon_x';
    $claims['entacc'] = 'entacc_y';
    $claims['act'] = ['iss' => $claims['iss'], 'sub' => 'user_admin', 'sid' => 'sess_admin'];
    $claims['jti'] = 'jti-1';
    $claims['aud'] = 'api-gateway';
    $claims['nonce'] = 'n-1';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->customClaims)->toBe([]);
});

it('defaults customClaims to an empty array when only reserved claims are present', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $verified = $verifier->verify($fixture->sign(validClaims()));

    expect($verified->customClaims)->toBe([]);
});

it('customClaim returns the supplied default when the key is missing', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['tier'] = 'pro';

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->customClaim('tier', 'free'))->toBe('pro');
    expect($verified->customClaim('plan', 'starter'))->toBe('starter');
    expect($verified->customClaim('missing'))->toBeNull();
});

it('preserves a null value stored in customClaims', function (): void {
    $fixture = new JwtFixture;
    $verifier = makeVerifier(new StaticBodyClient($fixture->jwksJson()));

    $claims = validClaims();
    $claims['feature_flag'] = null;

    $verified = $verifier->verify($fixture->sign($claims));

    expect($verified->customClaims)->toHaveKey('feature_flag');
    expect($verified->customClaim('feature_flag', 'fallback'))->toBeNull();
});
