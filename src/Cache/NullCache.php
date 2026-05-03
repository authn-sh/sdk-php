<?php

declare(strict_types=1);

namespace Authn\Sdk\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Default cache used by TokenVerifier when the caller doesn't pass a PSR-16 cache.
 *
 * Every read is a miss and every write is a no-op, so the JWKS document will be
 * re-fetched on every verify(). For production workloads, inject a real PSR-16 cache.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    /**
     * @param  iterable<string>  $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $default;
        }

        return $out;
    }

    /**
     * @param  iterable<string, mixed>  $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return true;
    }

    /**
     * @param  iterable<string>  $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }
}
