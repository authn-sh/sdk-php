<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class OauthProvider
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, string>  $additionalAuthorizationParams
     * @param  array<string, string>  $attributeMapping
     * @param  list<string>  $idTokenSigningAlgs
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $providerKind,
        public readonly string $providerKey,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly bool $allowSignIn,
        public readonly bool $allowSignUp,
        public readonly bool $blockEmailSubaddresses,
        public readonly string $clientId,
        public readonly array $scopes,
        public readonly array $additionalAuthorizationParams,
        public readonly array $attributeMapping,
        public readonly string $redirectUri,
        public readonly ?string $issuer,
        public readonly ?string $discoveryEndpoint,
        public readonly ?string $authorizationEndpoint,
        public readonly ?string $tokenEndpoint,
        public readonly ?string $userinfoEndpoint,
        public readonly ?string $jwksUri,
        public readonly array $idTokenSigningAlgs,
        public readonly ?string $userinfoMethod,
        public readonly ?string $userinfoAuth,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: self::stringField($payload, 'id'),
            providerKind: self::stringField($payload, 'provider_kind'),
            providerKey: self::stringField($payload, 'provider_key'),
            name: self::stringField($payload, 'name'),
            enabled: (bool) ($payload['enabled'] ?? false),
            allowSignIn: (bool) ($payload['allow_sign_in'] ?? false),
            allowSignUp: (bool) ($payload['allow_sign_up'] ?? false),
            blockEmailSubaddresses: (bool) ($payload['block_email_subaddresses'] ?? false),
            clientId: self::stringField($payload, 'client_id'),
            scopes: self::stringList($payload['scopes'] ?? []),
            additionalAuthorizationParams: self::stringMap($payload['additional_authorization_params'] ?? []),
            attributeMapping: self::stringMap($payload['attribute_mapping'] ?? []),
            redirectUri: self::stringField($payload, 'redirect_uri'),
            issuer: self::nullableString($payload['issuer'] ?? null),
            discoveryEndpoint: self::nullableString($payload['discovery_endpoint'] ?? null),
            authorizationEndpoint: self::nullableString($payload['authorization_endpoint'] ?? null),
            tokenEndpoint: self::nullableString($payload['token_endpoint'] ?? null),
            userinfoEndpoint: self::nullableString($payload['userinfo_endpoint'] ?? null),
            jwksUri: self::nullableString($payload['jwks_uri'] ?? null),
            idTokenSigningAlgs: self::stringList($payload['id_token_signing_algs'] ?? []),
            userinfoMethod: self::nullableString($payload['userinfo_method'] ?? null),
            userinfoAuth: self::nullableString($payload['userinfo_auth'] ?? null),
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

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }
}
