<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class PasskeysManager extends Manager
{
    public function list(?PasskeysListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/passkeys', [
            'query' => ($params ?? new PasskeysListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    public function get(string $passkeyId): Passkey
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/passkeys/' . rawurlencode($passkeyId),
        );

        return Passkey::fromPayload($payload);
    }

    public function update(string $passkeyId, string $nickname, ?string $idempotencyKey = null): Passkey
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/passkeys/' . rawurlencode($passkeyId),
            [
                'body' => ['nickname' => $nickname],
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return Passkey::fromPayload($payload);
    }

    public function delete(string $passkeyId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/passkeys/' . rawurlencode($passkeyId),
        );
    }
}
