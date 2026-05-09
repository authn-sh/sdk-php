<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class RedirectUrlsManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/redirect-urls', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $url): array
    {
        return $this->transport->send('POST', '/v1/redirect-urls', [
            'body' => ['url' => $url],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->transport->send('GET', '/v1/redirect-urls/' . rawurlencode($id));
    }

    public function delete(string $id): void
    {
        $this->transport->send('DELETE', '/v1/redirect-urls/' . rawurlencode($id));
    }
}
