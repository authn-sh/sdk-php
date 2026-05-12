<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Resources\ScimAttributeMapping;
use Authn\Sdk\Resources\ScimAttributeMappingsManager;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function scimAttributeMappingPayload(): array
{
    return [
        'organization_id' => 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'mapping' => [
            'userName' => 'email_address',
            'name.givenName' => 'first_name',
            'name.familyName' => 'last_name',
            'externalId' => 'external_id',
        ],
    ];
}

it('reads the SCIM attribute mapping for an organization', function (): void {
    $mock = (new MockTransport)->enqueue(body: scimAttributeMappingPayload());
    $manager = new ScimAttributeMappingsManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    $mapping = $manager->get();

    expect($mapping)->toBeInstanceOf(ScimAttributeMapping::class);
    expect($mapping->organizationId)->toBe('org_01HKX9SY9V7H7TF8C8K7J9X4ZB');
    expect($mapping->mapping)->toBe([
        'userName' => 'email_address',
        'name.givenName' => 'first_name',
        'name.familyName' => 'last_name',
        'externalId' => 'external_id',
    ]);
    expect($mock->lastRequest()->getMethod())->toBe('GET');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/organizations/org_01HKX9SY9V7H7TF8C8K7J9X4ZB/scim/attribute-mappings');
});

it('replaces the SCIM attribute mapping via PUT', function (): void {
    $payload = [
        'organization_id' => 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'mapping' => [
            'userName' => 'email_address',
            'name.givenName' => 'first_name',
            'name.familyName' => 'last_name',
            'externalId' => 'external_id',
            'urn:custom:department' => 'public_metadata.department',
        ],
    ];

    $mock = (new MockTransport)->enqueue(body: $payload);
    $manager = new ScimAttributeMappingsManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    $replaced = $manager->put([
        'userName' => 'email_address',
        'name.givenName' => 'first_name',
        'name.familyName' => 'last_name',
        'externalId' => 'external_id',
        'urn:custom:department' => 'public_metadata.department',
    ]);

    expect($replaced->mapping)->toHaveKey('urn:custom:department');
    expect($replaced->mapping['urn:custom:department'])->toBe('public_metadata.department');

    expect($mock->lastRequest()->getMethod())->toBe('PUT');
    expect((string) $mock->lastRequest()->getBody())->toContain('"mapping":{');
});

it('ignores non-string mapping entries in the response', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'organization_id' => 'org_x',
        'mapping' => [
            'userName' => 'email_address',
            'broken' => 42,
            13 => 'numeric_key',
        ],
    ]);
    $manager = new ScimAttributeMappingsManager($mock->transport(), 'org_x');

    $mapping = $manager->get();

    expect($mapping->mapping)->toBe(['userName' => 'email_address']);
});

it('exposes the SCIM endpoint URL for an organization', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['endpoint_url' => 'https://acme.authn.sh/scim/v2/']);
    $manager = new ScimAttributeMappingsManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    $endpoint = $manager->endpoint();

    expect($endpoint['endpoint_url'])->toBe('https://acme.authn.sh/scim/v2/');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/organizations/org_01HKX9SY9V7H7TF8C8K7J9X4ZB/scim/endpoint');
});

it('falls back to an empty endpoint_url when missing', function (): void {
    $mock = (new MockTransport)->enqueue(body: []);
    $manager = new ScimAttributeMappingsManager($mock->transport(), 'org_x');

    expect($manager->endpoint())->toBe(['endpoint_url' => '']);
});

it('Client::organizations()->scimAttributeMappings() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: scimAttributeMappingPayload());
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->organizations()->scimAttributeMappings('org_x'))
        ->toBeInstanceOf(ScimAttributeMappingsManager::class);
});
