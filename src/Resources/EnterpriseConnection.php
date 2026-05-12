<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseConnection
{
    /**
     * @param  list<string>  $domains
     * @param  array<string, string>  $attributeMapping
     * @param  list<string>  $oidcScopes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $protocol,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly ?string $organizationId,
        public readonly array $domains,
        public readonly string $defaultRole,
        public readonly array $attributeMapping,
        public readonly ?string $samlIdpEntityId,
        public readonly ?string $samlSsoUrl,
        public readonly ?string $samlIdpCertificate,
        public readonly ?string $samlSigningAlgorithm,
        public readonly ?string $samlAudienceUri,
        public readonly ?string $samlAcsUrl,
        public readonly ?string $samlSpEntityId,
        public readonly ?string $oidcIssuer,
        public readonly ?string $oidcDiscoveryEndpoint,
        public readonly ?string $oidcClientId,
        public readonly array $oidcScopes,
        public readonly ?string $oidcRedirectUri,
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
            protocol: self::stringField($payload, 'protocol'),
            name: self::stringField($payload, 'name'),
            enabled: (bool) ($payload['enabled'] ?? false),
            organizationId: self::nullableString($payload['organization_id'] ?? null),
            domains: self::stringList($payload['domains'] ?? []),
            defaultRole: self::stringField($payload, 'default_role'),
            attributeMapping: self::stringMap($payload['attribute_mapping'] ?? []),
            samlIdpEntityId: self::nullableString($payload['saml_idp_entity_id'] ?? null),
            samlSsoUrl: self::nullableString($payload['saml_sso_url'] ?? null),
            samlIdpCertificate: self::nullableString($payload['saml_idp_certificate'] ?? null),
            samlSigningAlgorithm: self::nullableString($payload['saml_signing_algorithm'] ?? null),
            samlAudienceUri: self::nullableString($payload['saml_audience_uri'] ?? null),
            samlAcsUrl: self::nullableString($payload['saml_acs_url'] ?? null),
            samlSpEntityId: self::nullableString($payload['saml_sp_entity_id'] ?? null),
            oidcIssuer: self::nullableString($payload['oidc_issuer'] ?? null),
            oidcDiscoveryEndpoint: self::nullableString($payload['oidc_discovery_endpoint'] ?? null),
            oidcClientId: self::nullableString($payload['oidc_client_id'] ?? null),
            oidcScopes: self::stringList($payload['oidc_scopes'] ?? []),
            oidcRedirectUri: self::nullableString($payload['oidc_redirect_uri'] ?? null),
            createdAt: self::intField($payload, 'created_at'),
            updatedAt: self::intField($payload, 'updated_at'),
            raw: $payload,
        );
    }

    public function isSaml(): bool
    {
        return $this->protocol === 'saml';
    }

    public function isOidc(): bool
    {
        return $this->protocol === 'oidc';
    }

    public function isInstanceWide(): bool
    {
        return $this->organizationId === null;
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
