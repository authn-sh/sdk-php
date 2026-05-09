<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class RolesManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/roles', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('POST', '/v1/roles', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $roleId): array
    {
        return $this->transport->send('GET', '/v1/roles/' . rawurlencode($roleId));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $roleId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('PATCH', '/v1/roles/' . rawurlencode($roleId), [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function delete(string $roleId): void
    {
        $this->transport->send('DELETE', '/v1/roles/' . rawurlencode($roleId));
    }

    /**
     * Replaces the role's permission set wholesale.
     *
     * @param  list<string>  $permissionKeys
     * @return array<string, mixed>
     */
    public function setPermissions(string $roleId, array $permissionKeys): array
    {
        return $this->transport->send('PUT', '/v1/roles/' . rawurlencode($roleId) . '/permissions', [
            'body' => ['permissions' => $permissionKeys],
        ]);
    }
}
