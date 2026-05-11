<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class Passkey
{
    /**
     * @param  list<string>  $transports
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nickname,
        public readonly array $transports,
        public readonly ?string $aaguid,
        public readonly bool $verified,
        public readonly ?int $lastUsedAt,
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
            nickname: self::stringField($payload, 'nickname'),
            transports: self::stringList($payload['transports'] ?? []),
            aaguid: self::nullableString($payload['aaguid'] ?? null),
            verified: (bool) ($payload['verified'] ?? false),
            lastUsedAt: self::nullableInt($payload['last_used_at'] ?? null),
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

    private static function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
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
}
