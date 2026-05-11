<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class ExternalAccountsManager extends Manager
{
    public function list(?ExternalAccountsListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/external-accounts', [
            'query' => ($params ?? new ExternalAccountsListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    public function get(string $externalAccountId): ExternalAccount
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/external-accounts/' . rawurlencode($externalAccountId),
        );

        return ExternalAccount::fromPayload($payload);
    }

    public function delete(string $externalAccountId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/external-accounts/' . rawurlencode($externalAccountId),
        );
    }
}
