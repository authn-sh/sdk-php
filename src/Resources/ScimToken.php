<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

class ScimToken
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $prefix,
        public readonly int $createdAt,
        public readonly ?int $revokedAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: self::stringField($payload, 'id'),
            organizationId: self::stringField($payload, 'organization_id'),
            name: self::stringField($payload, 'name'),
            prefix: self::stringField($payload, 'prefix'),
            createdAt: self::intField($payload, 'created_at'),
            revokedAt: isset($payload['revoked_at']) && is_int($payload['revoked_at']) ? $payload['revoked_at'] : null,
            raw: $payload,
        );
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function intField(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) ? $value : 0;
    }
}
