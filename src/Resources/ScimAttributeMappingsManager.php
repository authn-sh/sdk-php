<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Http\Transport;

final class ScimAttributeMappingsManager extends Manager
{
    public function __construct(Transport $transport, private readonly string $orgId)
    {
        parent::__construct($transport);
    }

    public function get(): ScimAttributeMapping
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/attribute-mappings',
        );

        return ScimAttributeMapping::fromPayload($payload);
    }

    /**
     * @param  array<string, string>  $mapping
     */
    public function put(array $mapping): ScimAttributeMapping
    {
        $payload = $this->transport->send(
            'PUT',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/attribute-mappings',
            ['body' => ['mapping' => $mapping]],
        );

        return ScimAttributeMapping::fromPayload($payload);
    }

    /**
     * @return array{endpoint_url: string}
     */
    public function endpoint(): array
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/organizations/' . rawurlencode($this->orgId) . '/scim/endpoint',
        );

        $url = isset($payload['endpoint_url']) && is_string($payload['endpoint_url']) ? $payload['endpoint_url'] : '';

        return ['endpoint_url' => $url];
    }
}
