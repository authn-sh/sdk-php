<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class SessionsManager extends Manager
{
    public function list(?SessionsListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/sessions', [
            'query' => ($params ?? new SessionsListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $sessionId): array
    {
        return $this->transport->send('GET', '/v1/sessions/' . rawurlencode($sessionId));
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(string $sessionId): array
    {
        return $this->transport->send('POST', '/v1/sessions/' . rawurlencode($sessionId) . '/revoke');
    }

    public function getToken(string $sessionId, ?string $template = null): string
    {
        $path = '/v1/sessions/' . rawurlencode($sessionId) . '/tokens';
        if ($template !== null) {
            $path .= '/' . rawurlencode($template);
        }

        $payload = $this->transport->send('POST', $path);

        return isset($payload['jwt']) && is_string($payload['jwt']) ? $payload['jwt'] : '';
    }
}
