<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseAccountsManager extends Manager
{
    public function list(?EnterpriseAccountsListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/enterprise-accounts', [
            'query' => ($params ?? new EnterpriseAccountsListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    public function get(string $enterpriseAccountId): EnterpriseAccount
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/enterprise-accounts/' . rawurlencode($enterpriseAccountId),
        );

        return EnterpriseAccount::fromPayload($payload);
    }

    public function delete(string $enterpriseAccountId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/enterprise-accounts/' . rawurlencode($enterpriseAccountId),
        );
    }
}
