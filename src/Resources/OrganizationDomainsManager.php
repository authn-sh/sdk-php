<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;
use Authn\Sdk\Util\Idempotency;

final class OrganizationDomainsManager extends Manager
{
    public function __construct(Transport $transport, private readonly string $orgId)
    {
        parent::__construct($transport);
    }

    public function list(): PaginatedList
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains',
        );

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, string $enrollmentMode = 'manual_invitation'): array
    {
        $data = ['name' => $name, 'enrollment_mode' => $enrollmentMode];

        return $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains',
            [
                'body' => $data,
                'idempotencyKey' => Idempotency::keyFor($data),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $domainId): array
    {
        return $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains/' . rawurlencode($domainId),
        );
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function update(string $domainId, array $patch): array
    {
        return $this->transport->send(
            'PATCH',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains/' . rawurlencode($domainId),
            ['body' => $patch],
        );
    }

    public function delete(string $domainId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains/' . rawurlencode($domainId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $domainId): array
    {
        return $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/domains/' . rawurlencode($domainId) . '/verify',
        );
    }
}
