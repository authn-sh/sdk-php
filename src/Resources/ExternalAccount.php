<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class ExternalAccount
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $publicMetadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $providerKey,
        public readonly string $providerUserId,
        public readonly ?string $emailAddress,
        public readonly array $scopes,
        public readonly array $publicMetadata,
        public readonly bool $verified,
        public readonly ?int $linkedAt,
        public readonly ?int $lastSignedInAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $scopes = $payload['scopes'] ?? [];
        $publicMetadata = $payload['public_metadata'] ?? [];
        $publicMetadataMap = [];
        if (is_array($publicMetadata)) {
            foreach ($publicMetadata as $k => $v) {
                if (is_string($k)) {
                    $publicMetadataMap[$k] = $v;
                }
            }
        }

        return new self(
            id: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '',
            provider: isset($payload['provider']) && is_string($payload['provider']) ? $payload['provider'] : '',
            providerKey: isset($payload['provider_key']) && is_string($payload['provider_key']) ? $payload['provider_key'] : '',
            providerUserId: isset($payload['provider_user_id']) && is_string($payload['provider_user_id']) ? $payload['provider_user_id'] : '',
            emailAddress: isset($payload['email_address']) && is_string($payload['email_address']) ? $payload['email_address'] : null,
            scopes: is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
            publicMetadata: $publicMetadataMap,
            verified: (bool) ($payload['verified'] ?? false),
            linkedAt: isset($payload['linked_at']) && is_int($payload['linked_at']) ? $payload['linked_at'] : null,
            lastSignedInAt: isset($payload['last_signed_in_at']) && is_int($payload['last_signed_in_at']) ? $payload['last_signed_in_at'] : null,
            raw: $payload,
        );
    }
}
