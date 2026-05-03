<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class InvitationsManager extends Manager
{
    public function list(?InvitationsListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/invitations', [
            'query' => ($params ?? new InvitationsListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('POST', '/v1/invitations', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $invitations
     * @return array<string, mixed>
     */
    public function bulkCreate(array $invitations, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('POST', '/v1/invitations/bulk', [
            'body' => ['invitations' => $invitations],
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($invitations),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(string $invitationId): array
    {
        return $this->transport->send('POST', '/v1/invitations/' . rawurlencode($invitationId) . '/revoke');
    }
}
