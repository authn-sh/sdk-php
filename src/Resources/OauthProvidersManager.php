<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class OauthProvidersManager extends Manager
{
    public function list(?OauthProvidersListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/oauth-providers', [
            'query' => ($params ?? new OauthProvidersListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): OauthProvider
    {
        $payload = $this->transport->send('POST', '/v1/oauth-providers', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);

        return OauthProvider::fromPayload($payload);
    }

    public function get(string $oauthProviderId): OauthProvider
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/oauth-providers/' . rawurlencode($oauthProviderId),
        );

        return OauthProvider::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $oauthProviderId, array $data, ?string $idempotencyKey = null): OauthProvider
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/oauth-providers/' . rawurlencode($oauthProviderId),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return OauthProvider::fromPayload($payload);
    }

    public function delete(string $oauthProviderId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/oauth-providers/' . rawurlencode($oauthProviderId),
        );
    }

    public function test(string $oauthProviderId): OauthProviderTestResult
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/oauth-providers/' . rawurlencode($oauthProviderId) . '/test',
        );

        return OauthProviderTestResult::fromPayload($payload);
    }
}
