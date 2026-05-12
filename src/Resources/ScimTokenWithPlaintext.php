<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class ScimTokenWithPlaintext extends ScimToken
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        string $id,
        string $organizationId,
        string $name,
        string $prefix,
        int $createdAt,
        ?int $revokedAt,
        array $raw,
        public readonly string $token,
    ) {
        parent::__construct($id, $organizationId, $name, $prefix, $createdAt, $revokedAt, $raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $token = $payload['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('ScimTokenWithPlaintext requires a non-empty token field on the payload.');
        }

        return new self(
            id: self::stringField($payload, 'id'),
            organizationId: self::stringField($payload, 'organization_id'),
            name: self::stringField($payload, 'name'),
            prefix: self::stringField($payload, 'prefix'),
            createdAt: self::intField($payload, 'created_at'),
            revokedAt: isset($payload['revoked_at']) && is_int($payload['revoked_at']) ? $payload['revoked_at'] : null,
            raw: $payload,
            token: $token,
        );
    }
}
