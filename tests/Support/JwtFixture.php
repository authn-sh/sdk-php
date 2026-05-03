<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

/**
 * Test helper: generates a fresh RSA keypair, can sign JWTs against it, and
 * exposes the matching JWKS document for the verifier to consume.
 */
final class JwtFixture
{
    public readonly JWK $jwk;

    public readonly string $kid;

    public function __construct(string $kid = 'test-kid-1')
    {
        $this->kid = $kid;
        $this->jwk = JWKFactory::createRSAKey(2048, [
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $kid,
        ]);
    }

    public function jwksJson(): string
    {
        $jwks = new JWKSet([$this->jwk->toPublic()]);

        return json_encode($jwks, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $headerOverrides
     */
    public function sign(array $claims, array $headerOverrides = []): string
    {
        $builder = new JWSBuilder(new AlgorithmManager([new RS256]));
        $header = array_merge(
            ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid],
            $headerOverrides,
        );

        $jws = $builder
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($this->jwk, $header)
            ->build();

        return (new CompactSerializer)->serialize($jws);
    }
}
