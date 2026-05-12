<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\EnterpriseConnection;
use Authn\Sdk\Resources\EnterpriseConnectionsListParams;
use Authn\Sdk\Resources\EnterpriseConnectionsManager;
use Authn\Sdk\Resources\EnterpriseConnectionTestResult;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function samlEnterpriseConnectionPayload(): array
{
    return [
        'id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'enterprise_connection',
        'protocol' => 'saml',
        'name' => 'Acme Okta',
        'enabled' => true,
        'organization_id' => 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'domains' => ['acme.example'],
        'default_role' => 'org:member',
        'attribute_mapping' => [
            'email_address' => 'urn:oid:0.9.2342.19200300.100.1.3',
            'first_name' => 'urn:oid:2.5.4.42',
            'last_name' => 'urn:oid:2.5.4.4',
            'provider_user_id' => 'nameid',
        ],
        'saml_idp_entity_id' => 'https://idp.acme.example/saml/metadata',
        'saml_sso_url' => 'https://idp.acme.example/saml/sso',
        'saml_idp_certificate' => "-----BEGIN CERTIFICATE-----\nMIID...\n-----END CERTIFICATE-----",
        'saml_signing_algorithm' => 'RSA_SHA256',
        'saml_audience_uri' => 'https://acme.authn.sh/v1/saml/entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA/metadata',
        'saml_acs_url' => 'https://acme.authn.sh/v1/saml/entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA/acs',
        'saml_sp_entity_id' => 'https://acme.authn.sh/v1/saml/entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA/metadata',
        'oidc_issuer' => null,
        'oidc_discovery_endpoint' => null,
        'oidc_client_id' => null,
        'oidc_scopes' => [],
        'oidc_redirect_uri' => null,
        'created_at' => 1_700_000_000_000,
        'updated_at' => 1_700_000_001_000,
    ];
}

/**
 * @return array<string, mixed>
 */
function oidcEnterpriseConnectionPayload(): array
{
    return [
        'id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZC',
        'object' => 'enterprise_connection',
        'protocol' => 'oidc',
        'name' => 'Workspace-wide Okta',
        'enabled' => true,
        'organization_id' => null,
        'domains' => ['staff.example'],
        'default_role' => 'org:member',
        'attribute_mapping' => [
            'email_address' => 'email',
            'first_name' => 'given_name',
            'last_name' => 'family_name',
            'provider_user_id' => 'sub',
        ],
        'saml_idp_entity_id' => null,
        'saml_sso_url' => null,
        'saml_idp_certificate' => null,
        'saml_signing_algorithm' => null,
        'saml_audience_uri' => null,
        'saml_acs_url' => null,
        'saml_sp_entity_id' => null,
        'oidc_issuer' => 'https://staff-corp.okta.com',
        'oidc_discovery_endpoint' => 'https://staff-corp.okta.com/.well-known/openid-configuration',
        'oidc_client_id' => '0oa1abcdEFGHIJKLM2x7',
        'oidc_scopes' => ['openid', 'profile', 'email', 'groups'],
        'oidc_redirect_uri' => 'https://staff.authn.sh/v1/enterprise-sso-callback',
        'created_at' => 1_700_000_000_000,
        'updated_at' => 1_700_000_001_000,
    ];
}

it('lists enterprise connections with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [samlEnterpriseConnectionPayload(), oidcEnterpriseConnectionPayload()],
        'total_count' => 2,
    ]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $list = $manager->list(new EnterpriseConnectionsListParams(limit: 10));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/enterprise-connections');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
});

it('filters list by organization_id', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $manager->list(new EnterpriseConnectionsListParams(
        organizationId: 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
    ));

    expect((string) $mock->lastRequest()->getUri())
        ->toContain('organization_id=org_01HKX9SY9V7H7TF8C8K7J9X4ZB');
});

it('filters list to instance-wide rows with the literal "null" value', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $manager->list(new EnterpriseConnectionsListParams(organizationId: 'null'));

    expect((string) $mock->lastRequest()->getUri())
        ->toContain('organization_id=null');
});

it('creates a SAML enterprise connection with an idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, samlEnterpriseConnectionPayload());
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $created = $manager->create([
        'protocol' => 'saml',
        'name' => 'Acme Okta',
        'organization_id' => 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'domains' => ['acme.example'],
        'saml_idp_entity_id' => 'https://idp.acme.example/saml/metadata',
        'saml_sso_url' => 'https://idp.acme.example/saml/sso',
        'saml_idp_certificate' => '-----BEGIN CERTIFICATE-----',
    ], idempotencyKey: 'idem-1');

    expect($created)->toBeInstanceOf(EnterpriseConnection::class);
    expect($created->id)->toBe('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($created->protocol)->toBe('saml');
    expect($created->isSaml())->toBeTrue();
    expect($created->isOidc())->toBeFalse();
    expect($created->isInstanceWide())->toBeFalse();
    expect($created->samlAcsUrl)->toContain('/acs');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $mockA = (new MockTransport)->enqueue(201, oidcEnterpriseConnectionPayload());
    $mockB = (new MockTransport)->enqueue(201, oidcEnterpriseConnectionPayload());

    (new EnterpriseConnectionsManager($mockA->transport()))
        ->create(['protocol' => 'oidc', 'name' => 'Acme', 'oidc_client_id' => 'x']);
    (new EnterpriseConnectionsManager($mockB->transport()))
        ->create(['name' => 'Acme', 'protocol' => 'oidc', 'oidc_client_id' => 'x']);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('parses an instance-wide OIDC connection', function (): void {
    $mock = (new MockTransport)->enqueue(body: oidcEnterpriseConnectionPayload());
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $got = $manager->get('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZC');

    expect($got->isOidc())->toBeTrue();
    expect($got->isInstanceWide())->toBeTrue();
    expect($got->organizationId)->toBeNull();
    expect($got->oidcScopes)->toBe(['openid', 'profile', 'email', 'groups']);
    expect($got->oidcRedirectUri)->toBe('https://staff.authn.sh/v1/enterprise-sso-callback');
});

it('gets, updates, deletes an enterprise connection', function (): void {
    $payload = samlEnterpriseConnectionPayload();
    $disabled = ['enabled' => false] + $payload;

    $mock = (new MockTransport)
        ->enqueue(body: $payload)
        ->enqueue(body: $disabled)
        ->enqueue(204);

    $manager = new EnterpriseConnectionsManager($mock->transport());

    $got = $manager->get('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($got->enabled)->toBeTrue();

    $updated = $manager->update('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA', ['enabled' => false]);
    expect($updated->enabled)->toBeFalse();

    $manager->delete('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())
        ->toEndWith('/v1/enterprise-connections/entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('test() returns probe result with authorize_url and discovery_status', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'authorize_url' => 'https://idp.acme.example/saml/sso?SAMLRequest=fVJBb%2FIwDP0r',
        'discovery_status' => 200,
        'errors' => [],
    ]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $result = $manager->test('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($result)->toBeInstanceOf(EnterpriseConnectionTestResult::class);
    expect($result->authorizeUrl)->toContain('SAMLRequest=');
    expect($result->discoveryStatus)->toBe(200);
    expect($result->passed())->toBeTrue();
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/enterprise-connections/entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA/test');
});

it('test() surfaces probe errors', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'authorize_url' => '',
        'discovery_status' => 502,
        'errors' => [
            [
                'code' => 'enterprise_connection_discovery_unreachable',
                'message' => 'Could not reach the OIDC discovery endpoint',
                'long_message' => 'The IdP returned 502.',
            ],
        ],
    ]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $result = $manager->test('entcon_xyz');

    expect($result->passed())->toBeFalse();
    expect($result->discoveryStatus)->toBe(502);
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->code)->toBe('enterprise_connection_discovery_unreachable');
});

it('test() handles null discovery_status (no discovery URL configured)', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'authorize_url' => '',
        'discovery_status' => null,
        'errors' => [
            [
                'code' => 'enterprise_connection_saml_metadata_invalid',
                'message' => 'SAML metadata did not parse',
                'long_message' => 'Signature verification failed.',
            ],
        ],
    ]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    $result = $manager->test('entcon_xyz');

    expect($result->discoveryStatus)->toBeNull();
    expect($result->errors[0]->code)->toBe('enterprise_connection_saml_metadata_invalid');
});

it('in-use deletion surfaces as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(409, [
        'errors' => [['code' => 'enterprise_connection_in_use', 'message' => 'still linked', 'long_message' => '...']],
    ]);
    $manager = new EnterpriseConnectionsManager($mock->transport());

    expect(fn () => $manager->delete('entcon_xyz'))->toThrow(ApiException::class);
});

it('Client::enterpriseConnections() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->enterpriseConnections())->toBeInstanceOf(EnterpriseConnectionsManager::class);
});
