<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use RuntimeException;

final class OauthApplicationWithSecret extends OauthApplication
{
    /**
     * @param  list<string>  $callbackUrls
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        string $id,
        string $name,
        string $clientId,
        array $callbackUrls,
        array $scopes,
        bool $isPublic,
        int $createdAt,
        int $updatedAt,
        array $raw,
        public readonly string $clientSecret,
    ) {
        parent::__construct($id, $name, $clientId, $callbackUrls, $scopes, $isPublic, $createdAt, $updatedAt, $raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $secret = $payload['client_secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('OauthApplicationWithSecret requires a non-empty client_secret on the payload.');
        }

        return new self(
            id: self::stringField($payload, 'id'),
            name: self::stringField($payload, 'name'),
            clientId: self::stringField($payload, 'client_id'),
            callbackUrls: self::stringList($payload['callback_urls'] ?? []),
            scopes: self::stringList($payload['scopes'] ?? []),
            isPublic: (bool) ($payload['is_public'] ?? false),
            createdAt: self::intField($payload, 'created_at'),
            updatedAt: self::intField($payload, 'updated_at'),
            raw: $payload,
            clientSecret: $secret,
        );
    }
}
