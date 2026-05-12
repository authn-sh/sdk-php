<?php

declare(strict_types=1);

namespace Authn\Sdk\Tokens;

use Authn\Sdk\Cache\NullCache;
use Authn\Sdk\Util\Json;
use Authn\Sdk\Util\PublishableKey;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Verifies session JWTs issued by an authn.sh Frontend API.
 *
 * Fetches the FAPI's JWKS once and caches it in PSR-16 for `jwksCacheTtlSeconds`.
 * On an unknown `kid` the cache is bypassed and the JWKS re-fetched once;
 * still missing → `TokenInvalidException`.
 */
final class TokenVerifier
{
    public const SUPPORTED_ALGORITHMS = ['RS256', 'ES256', 'ES384', 'ES512'];

    private readonly string $frontendApiUrl;

    private readonly ClientInterface $http;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly CacheInterface $cache;

    private readonly JWSVerifier $verifier;

    private readonly CompactSerializer $serializer;

    public function __construct(
        string $publishableKey,
        ?string $frontendApiUrl = null,
        ?CacheInterface $cache = null,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        private readonly int $jwksCacheTtlSeconds = 600,
        private readonly int $allowedClockSkewSeconds = 5,
    ) {
        $this->frontendApiUrl = $frontendApiUrl !== null && $frontendApiUrl !== ''
            ? rtrim($frontendApiUrl, '/')
            : PublishableKey::frontendApiUrl($publishableKey);

        $this->cache = $cache ?? new NullCache;
        $this->http = $http ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();

        $this->verifier = new JWSVerifier(new AlgorithmManager([
            new RS256,
            new ES256,
            new ES384,
            new ES512,
        ]));
        $this->serializer = new CompactSerializer;
    }

    /**
     * @param  list<string>  $expectedAzp  if non-empty, the token's `azp` claim must match one of these
     *
     * @throws TokenInvalidException
     */
    public function verify(string $jwt, array $expectedAzp = []): VerifiedClaims
    {
        try {
            $jws = $this->serializer->unserialize($jwt);
        } catch (Throwable $e) {
            throw new TokenInvalidException('Malformed JWT: ' . $e->getMessage(), 0, $e);
        }

        $signature = $jws->getSignature(0);
        $header = $signature->getProtectedHeader();
        $kid = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : null;
        $alg = isset($header['alg']) && is_string($header['alg']) ? $header['alg'] : null;

        if ($alg === null || ! in_array($alg, self::SUPPORTED_ALGORITHMS, true)) {
            throw new TokenInvalidException('Unsupported JWT algorithm: ' . ($alg ?? '<missing>'));
        }

        $jwk = $this->resolveKey($kid);

        if (! $this->verifier->verifyWithKey($jws, $jwk, 0)) {
            throw new TokenInvalidException('Invalid JWT signature.');
        }

        $payload = $jws->getPayload();
        $claims = $payload === null ? [] : Json::decode($payload);
        if ($claims === []) {
            throw new TokenInvalidException('JWT payload is not a JSON object.');
        }

        return $this->buildVerifiedClaims($claims, $expectedAzp);
    }

    /**
     * @param  list<string>  $expectedAzp
     */
    public function tryVerify(string $jwt, array $expectedAzp = []): ?VerifiedClaims
    {
        try {
            return $this->verify($jwt, $expectedAzp);
        } catch (TokenInvalidException) {
            return null;
        }
    }

    private function resolveKey(?string $kid): JWK
    {
        $jwks = $this->getJwksFromCache();
        $key = $jwks !== null ? $this->findKey($jwks, $kid) : null;

        if ($key === null) {
            $jwks = $this->refreshJwks();
            $key = $this->findKey($jwks, $kid);
        }

        if ($key === null) {
            throw new TokenInvalidException('No matching JWK for kid ' . ($kid ?? '<none>'));
        }

        return $key;
    }

    private function getJwksFromCache(): ?JWKSet
    {
        $cached = $this->cache->get($this->cacheKey());
        if (! is_string($cached) || $cached === '') {
            return null;
        }

        try {
            return JWKSet::createFromJson($cached);
        } catch (Throwable) {
            return null;
        }
    }

    private function refreshJwks(): JWKSet
    {
        $url = $this->frontendApiUrl . '/.well-known/jwks.json';
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TokenInvalidException('Failed to fetch JWKS: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new TokenInvalidException('Failed to fetch JWKS: HTTP ' . $response->getStatusCode());
        }

        $body = (string) $response->getBody();

        try {
            $jwks = JWKSet::createFromJson($body);
        } catch (Throwable $e) {
            throw new TokenInvalidException('Malformed JWKS document: ' . $e->getMessage(), 0, $e);
        }

        $this->cache->set($this->cacheKey(), $body, $this->jwksCacheTtlSeconds);

        return $jwks;
    }

    private function findKey(JWKSet $jwks, ?string $kid): ?JWK
    {
        if ($kid === null) {
            if ($jwks->count() !== 1) {
                return null;
            }

            $key = $jwks->selectKey('sig');
            if ($key !== null) {
                return $key;
            }

            foreach ($jwks->all() as $jwk) {
                return $jwk;
            }

            return null;
        }

        try {
            return $jwks->get($kid);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $expectedAzp
     */
    private function buildVerifiedClaims(array $claims, array $expectedAzp): VerifiedClaims
    {
        $now = time();
        $skew = $this->allowedClockSkewSeconds;

        $iss = isset($claims['iss']) && is_string($claims['iss']) ? $claims['iss'] : null;
        if ($iss === null || rtrim($iss, '/') !== rtrim($this->frontendApiUrl, '/')) {
            throw new TokenInvalidException('JWT issuer does not match the configured Frontend API URL.');
        }

        $exp = isset($claims['exp']) && is_int($claims['exp']) ? $claims['exp'] : null;
        if ($exp === null) {
            throw new TokenInvalidException('JWT is missing the exp claim.');
        }
        if ($exp + $skew < $now) {
            throw new TokenInvalidException('JWT has expired.');
        }

        $iat = isset($claims['iat']) && is_int($claims['iat']) ? $claims['iat'] : null;
        if ($iat === null) {
            throw new TokenInvalidException('JWT is missing the iat claim.');
        }
        if ($iat - $skew > $now) {
            throw new TokenInvalidException('JWT iat is in the future.');
        }

        $nbf = isset($claims['nbf']) && is_int($claims['nbf']) ? $claims['nbf'] : null;
        if ($nbf !== null && $nbf - $skew > $now) {
            throw new TokenInvalidException('JWT is not yet valid (nbf in the future).');
        }

        $sub = isset($claims['sub']) && is_string($claims['sub']) ? $claims['sub'] : '';
        $sid = isset($claims['sid']) && is_string($claims['sid']) ? $claims['sid'] : '';
        if ($sub === '' || $sid === '') {
            throw new TokenInvalidException('JWT is missing the sub or sid claim.');
        }

        $azp = isset($claims['azp']) && is_string($claims['azp']) ? $claims['azp'] : null;
        if ($expectedAzp !== [] && ! in_array($azp, $expectedAzp, true)) {
            throw new TokenInvalidException('JWT azp does not match any expected value.');
        }

        /** @var array{iss: string, sub: string, sid: string}|null $actor */
        $actor = isset($claims['act']) && is_array($claims['act']) ? $this->normalizeActor($claims['act']) : null;
        /** @var array{id: string, slg?: string, rol?: string, per?: array<int, string>}|null $org */
        $org = isset($claims['org']) && is_array($claims['org']) ? $this->normalizeOrg($claims['org']) : null;
        $organization = $org !== null ? $this->buildOrganization($org) : null;

        [$firstFactorAgeSeconds, $secondFactorAgeSeconds, $twoFactorVerified] = $this->parseFva($claims);

        $phoneNumberVerified = isset($claims['pnv']) && $claims['pnv'] === true;
        $defaultSecondFactor = $this->parseDefaultSecondFactor($claims);

        $passkeyVerified = isset($claims['pkv']) && $claims['pkv'] === true;
        $passkeyCount = isset($claims['pkc']) && is_int($claims['pkc']) && $claims['pkc'] >= 0
            ? $claims['pkc']
            : 0;

        $enterpriseConnectionId = isset($claims['entcon']) && is_string($claims['entcon']) && $claims['entcon'] !== ''
            ? $claims['entcon']
            : null;
        $enterpriseAccountId = isset($claims['entacc']) && is_string($claims['entacc']) && $claims['entacc'] !== ''
            ? $claims['entacc']
            : null;

        return new VerifiedClaims(
            sub: $sub,
            sid: $sid,
            iss: $iss,
            azp: $azp,
            exp: $exp,
            iat: $iat,
            nbf: $nbf,
            actor: $actor,
            org: $org,
            wasTest: isset($claims['was_test']) && $claims['was_test'] === true,
            raw: $claims,
            organization: $organization,
            twoFactorVerified: $twoFactorVerified,
            secondFactorAgeSeconds: $secondFactorAgeSeconds,
            firstFactorAgeSeconds: $firstFactorAgeSeconds,
            phoneNumberVerified: $phoneNumberVerified,
            defaultSecondFactor: $defaultSecondFactor,
            passkeyVerified: $passkeyVerified,
            passkeyCount: $passkeyCount,
            enterpriseConnectionId: $enterpriseConnectionId,
            enterpriseAccountId: $enterpriseAccountId,
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function parseDefaultSecondFactor(array $claims): ?string
    {
        if (! isset($claims['dsf']) || ! is_string($claims['dsf'])) {
            return null;
        }

        $valid = [
            VerifiedClaims::SECOND_FACTOR_TOTP,
            VerifiedClaims::SECOND_FACTOR_PHONE_CODE,
            VerifiedClaims::SECOND_FACTOR_BACKUP_CODE,
        ];

        return in_array($claims['dsf'], $valid, true) ? $claims['dsf'] : null;
    }

    /**
     * @param  array<int|string, mixed>  $act
     * @return array{iss: string, sub: string, sid: string}|null
     */
    private function normalizeActor(array $act): ?array
    {
        $iss = isset($act['iss']) && is_string($act['iss']) ? $act['iss'] : null;
        $sub = isset($act['sub']) && is_string($act['sub']) ? $act['sub'] : null;
        $sid = isset($act['sid']) && is_string($act['sid']) ? $act['sid'] : null;
        if ($iss === null || $sub === null || $sid === null) {
            return null;
        }

        return ['iss' => $iss, 'sub' => $sub, 'sid' => $sid];
    }

    /**
     * @param  array<int|string, mixed>  $org
     * @return array{id: string, slg?: string, rol?: string, per?: array<int, string>}|null
     */
    private function normalizeOrg(array $org): ?array
    {
        $id = isset($org['id']) && is_string($org['id']) ? $org['id'] : null;
        if ($id === null) {
            return null;
        }

        $out = ['id' => $id];
        if (isset($org['slg']) && is_string($org['slg'])) {
            $out['slg'] = $org['slg'];
        }
        if (isset($org['rol']) && is_string($org['rol'])) {
            $out['rol'] = $org['rol'];
        }
        if (isset($org['per']) && is_array($org['per'])) {
            $perms = [];
            foreach ($org['per'] as $p) {
                if (is_string($p)) {
                    $perms[] = $p;
                }
            }
            $out['per'] = $perms;
        }

        return $out;
    }

    /**
     * @param  array{id: string, slg?: string, rol?: string, per?: array<int, string>}  $org
     */
    private function buildOrganization(array $org): Organization
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_unique($org['per'] ?? []));

        return new Organization(
            id: $org['id'],
            slug: $org['slg'] ?? null,
            role: $org['rol'] ?? null,
            permissions: $permissions,
        );
    }

    /**
     * Parses the `fva` ([first-factor-age, second-factor-age]) claim.
     *
     * Returns [firstFactorAgeSeconds, secondFactorAgeSeconds, twoFactorVerified].
     * -1 in the JWT means "not verified" → null. twoFactorVerified = fva[1] !== -1.
     *
     * @param  array<string, mixed>  $claims
     * @return array{?int, ?int, bool}
     */
    private function parseFva(array $claims): array
    {
        if (! isset($claims['fva']) || ! is_array($claims['fva'])) {
            return [null, null, false];
        }

        $fva = array_values($claims['fva']);
        $first = isset($fva[0]) && is_int($fva[0]) ? $fva[0] : null;
        $second = isset($fva[1]) && is_int($fva[1]) ? $fva[1] : null;

        return [
            $first !== null && $first !== -1 ? $first : null,
            $second !== null && $second !== -1 ? $second : null,
            $second !== null && $second !== -1,
        ];
    }

    private function cacheKey(): string
    {
        return 'authn.jwks.' . hash('sha256', $this->frontendApiUrl);
    }
}
