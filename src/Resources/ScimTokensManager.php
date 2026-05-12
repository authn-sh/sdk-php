<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;
use Authn\Sdk\Util\Idempotency;

final class ScimTokensManager extends Manager
{
    public function __construct(Transport $transport, private readonly string $orgId)
    {
        parent::__construct($transport);
    }

    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/tokens',
            ['query' => ($params ?? new ListParams)->toQuery()],
        );

        return PaginatedList::fromResponse($payload);
    }

    public function issue(string $name, ?string $idempotencyKey = null): ScimTokenWithPlaintext
    {
        $data = ['name' => $name];

        $payload = $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/tokens',
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
            ],
        );

        return ScimTokenWithPlaintext::fromPayload($payload);
    }

    public function revoke(string $scimTokenId): ScimToken
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/tokens/' . rawurlencode($scimTokenId) . '/revoke',
        );

        return ScimToken::fromPayload($payload);
    }
}
