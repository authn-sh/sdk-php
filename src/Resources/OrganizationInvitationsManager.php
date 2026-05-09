<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;
use Authn\Sdk\Util\Idempotency;

final class OrganizationInvitationsManager extends Manager
{
    public function __construct(Transport $transport, private readonly string $orgId)
    {
        parent::__construct($transport);
    }

    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/invitations',
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
            '/v1/organizations/' . rawurlencode($this->orgId) . '/invitations',
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $invitations
     * @return array<string, mixed>
     */
    public function bulkCreate(array $invitations, ?string $idempotencyKey = null): array
    {
        return $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/invitations/bulk',
            [
                'body' => ['invitations' => $invitations],
                'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($invitations),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(string $invitationId): array
    {
        return $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/invitations/' . rawurlencode($invitationId) . '/revoke',
        );
    }
}
