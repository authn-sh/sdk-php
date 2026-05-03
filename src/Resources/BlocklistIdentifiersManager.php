<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class BlocklistIdentifiersManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/blocklist_identifiers', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->transport->send('POST', '/v1/blocklist_identifiers', [
            'body' => $data,
        ]);
    }

    public function delete(string $id): void
    {
        $this->transport->send('DELETE', '/v1/blocklist_identifiers/' . rawurlencode($id));
    }
}
