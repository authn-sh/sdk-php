<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class OrganizationsManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/organizations', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('POST', '/v1/organizations', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $orgId): array
    {
        return $this->transport->send('GET', '/v1/organizations/' . rawurlencode($orgId));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $orgId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('PATCH', '/v1/organizations/' . rawurlencode($orgId), [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function delete(string $orgId): void
    {
        $this->transport->send('DELETE', '/v1/organizations/' . rawurlencode($orgId));
    }

    public function members(string $orgId): OrganizationMembershipsManager
    {
        return new OrganizationMembershipsManager($this->transport, $orgId);
    }

    public function invitations(string $orgId): OrganizationInvitationsManager
    {
        return new OrganizationInvitationsManager($this->transport, $orgId);
    }

    public function domains(string $orgId): OrganizationDomainsManager
    {
        return new OrganizationDomainsManager($this->transport, $orgId);
    }

    public function scimTokens(string $orgId): ScimTokensManager
    {
        return new ScimTokensManager($this->transport, $orgId);
    }

    public function scimAttributeMappings(string $orgId): ScimAttributeMappingsManager
    {
        return new ScimAttributeMappingsManager($this->transport, $orgId);
    }
}
