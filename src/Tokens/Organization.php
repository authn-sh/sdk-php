<?php

declare(strict_types=1);

namespace Authn\Sdk\Tokens;

final class Organization
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $slug,
        public readonly ?string $role,
        public readonly array $permissions,
    ) {}

    public function hasRole(string $key): bool
    {
        return $this->role === $key;
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions, true);
    }
}
