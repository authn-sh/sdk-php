<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

class OauthApplication
{
    /**
     * @param  list<string>  $callbackUrls
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $clientId,
        public readonly array $callbackUrls,
        public readonly array $scopes,
        public readonly bool $isPublic,
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
            name: self::stringField($payload, 'name'),
            clientId: self::stringField($payload, 'client_id'),
            callbackUrls: self::stringList($payload['callback_urls'] ?? []),
            scopes: self::stringList($payload['scopes'] ?? []),
            isPublic: (bool) ($payload['is_public'] ?? false),
            createdAt: self::intField($payload, 'created_at'),
            updatedAt: self::intField($payload, 'updated_at'),
            raw: $payload,
        );
    }

    public function isConfidential(): bool
    {
        return ! $this->isPublic;
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

    /**
     * @return list<string>
     */
    protected static function stringList(mixed $value): array
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
}
