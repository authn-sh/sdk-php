<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class JwtTemplatesManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/jwt-templates', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): JwtTemplate
    {
        $payload = $this->transport->send('POST', '/v1/jwt-templates', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);

        return JwtTemplate::fromPayload($payload);
    }

    public function get(string $jwtTemplateId): JwtTemplate
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/jwt-templates/' . rawurlencode($jwtTemplateId),
        );

        return JwtTemplate::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $jwtTemplateId, array $data, ?string $idempotencyKey = null): JwtTemplate
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/jwt-templates/' . rawurlencode($jwtTemplateId),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return JwtTemplate::fromPayload($payload);
    }

    public function delete(string $jwtTemplateId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/jwt-templates/' . rawurlencode($jwtTemplateId),
        );
    }
}
