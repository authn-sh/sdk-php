<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseAccount
{
    /**
     * @param  array<string, mixed>  $publicMetadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $enterpriseConnectionId,
        public readonly string $providerUserId,
        public readonly ?string $emailAddress,
        public readonly bool $verified,
        public readonly array $publicMetadata,
        public readonly int $linkedAt,
        public readonly ?int $lastSignedInAt,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $publicMetadata = [];
        if (isset($payload['public_metadata']) && is_array($payload['public_metadata'])) {
            foreach ($payload['public_metadata'] as $key => $value) {
                if (is_string($key)) {
                    $publicMetadata[$key] = $value;
                }
            }
        }

        $lastSignedInAt = $payload['last_signed_in_at'] ?? null;
        if (! is_int($lastSignedInAt)) {
            $lastSignedInAt = null;
        }

        return new self(
            id: self::stringField($payload, 'id'),
            enterpriseConnectionId: self::stringField($payload, 'enterprise_connection_id'),
            providerUserId: self::stringField($payload, 'provider_user_id'),
            emailAddress: self::nullableString($payload['email_address'] ?? null),
            verified: (bool) ($payload['verified'] ?? false),
            publicMetadata: $publicMetadata,
            linkedAt: self::intField($payload, 'linked_at'),
            lastSignedInAt: $lastSignedInAt,
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

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
