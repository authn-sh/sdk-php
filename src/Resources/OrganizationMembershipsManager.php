<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;
use Authn\Sdk\Util\Idempotency;

final class OrganizationMembershipsManager extends Manager
{
    public function __construct(Transport $transport, private readonly string $orgId)
    {
        parent::__construct($transport);
    }

    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/memberships',
            ['query' => ($params ?? new ListParams)->toQuery()],
        );

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/memberships',
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $userId, string $role, ?string $idempotencyKey = null): array
    {
        return $this->transport->send(
            'PATCH',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/memberships/' . rawurlencode($userId),
            [
                'body' => ['role' => $role],
                'idempotencyKey' => $idempotencyKey,
            ],
        );
    }

    public function delete(string $userId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/memberships/' . rawurlencode($userId),
        );
    }
}
