<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class JwtTemplate
{
    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $claims,
        public readonly int $lifetime,
        public readonly int $allowedClockSkew,
        public readonly string $signingAlgorithm,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $claims = [];
        if (isset($payload['claims']) && is_array($payload['claims'])) {
            foreach ($payload['claims'] as $key => $value) {
                if (is_string($key)) {
                    $claims[$key] = $value;
                }
            }
        }

        return new self(
            id: self::stringField($payload, 'id'),
            name: self::stringField($payload, 'name'),
            claims: $claims,
            lifetime: self::intField($payload, 'lifetime'),
            allowedClockSkew: self::intField($payload, 'allowed_clock_skew'),
            signingAlgorithm: self::stringField($payload, 'signing_algorithm'),
            createdAt: self::intField($payload, 'created_at'),
            updatedAt: self::intField($payload, 'updated_at'),
            raw: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function intField(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) ? $value : 0;
    }
}
