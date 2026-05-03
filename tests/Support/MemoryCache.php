<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

final class MemoryCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: int|null}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = null;
        if (is_int($ttl)) {
            $expiresAt = time() + $ttl;
        } elseif ($ttl instanceof DateInterval) {
            $expiresAt = (new \DateTimeImmutable)->add($ttl)->getTimestamp();
        }

        $this->store[$key] = ['value' => $value, 'expiresAt' => $expiresAt];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

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
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    /**
     * @param  iterable<string, mixed>  $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param  iterable<string>  $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (! isset($this->store[$key])) {
            return false;
        }

        $expiresAt = $this->store[$key]['expiresAt'];
        if ($expiresAt !== null && $expiresAt < time()) {
            unset($this->store[$key]);

            return false;
        }

        return true;
    }
}
