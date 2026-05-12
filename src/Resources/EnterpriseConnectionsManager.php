<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class EnterpriseConnectionsManager extends Manager
{
    public function list(?EnterpriseConnectionsListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/enterprise-connections', [
            'query' => ($params ?? new EnterpriseConnectionsListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): EnterpriseConnection
    {
        $payload = $this->transport->send('POST', '/v1/enterprise-connections', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);

        return EnterpriseConnection::fromPayload($payload);
    }

    public function get(string $enterpriseConnectionId): EnterpriseConnection
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/enterprise-connections/' . rawurlencode($enterpriseConnectionId),
        );

        return EnterpriseConnection::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $enterpriseConnectionId, array $data, ?string $idempotencyKey = null): EnterpriseConnection
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/enterprise-connections/' . rawurlencode($enterpriseConnectionId),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return EnterpriseConnection::fromPayload($payload);
    }

    public function delete(string $enterpriseConnectionId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/enterprise-connections/' . rawurlencode($enterpriseConnectionId),
        );
    }

    public function test(string $enterpriseConnectionId): EnterpriseConnectionTestResult
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/enterprise-connections/' . rawurlencode($enterpriseConnectionId) . '/test',
        );

        return EnterpriseConnectionTestResult::fromPayload($payload);
    }
}
