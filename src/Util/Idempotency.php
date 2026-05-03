<?php

declare(strict_types=1);

namespace Authn\Sdk\Util;

final class Idempotency
{
    /**
     * Deterministic idempotency key derived from a payload.
     *
     * Used by managers when the caller doesn't supply their own key — the same
     * payload sent twice produces the same key, which the BAPI deduplicates.
     *
     * @param  array<int|string, mixed>  $payload
     */
    public static function keyFor(array $payload): string
    {
        $normalized = self::sortRecursive($payload);

        return 'authn-' . hash('sha256', Json::encode($normalized));
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private static function sortRecursive(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $v): mixed => is_array($v) ? self::sortRecursive($v) : $v,
                $value,
            );
        }

        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sortRecursive($v);
            }
        }

        return $value;
    }
}
