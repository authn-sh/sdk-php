<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\OrganizationDomainsManager;
use Authn\Sdk\Resources\OrganizationInvitationsManager;
use Authn\Sdk\Resources\OrganizationMembershipsManager;
use Authn\Sdk\Resources\OrganizationsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists organizations with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'org_1'], ['id' => 'org_2']],
        'total_count' => 2,
    ]);
    $orgs = new OrganizationsManager($mock->transport());

    $list = $orgs->list(new ListParams(limit: 10));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/organizations');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
});

it('creates an organization with an idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'org_1', 'name' => 'Acme']);
    $orgs = new OrganizationsManager($mock->transport());

    $created = $orgs->create(['name' => 'Acme'], idempotencyKey: 'idem-1');

    expect($created['id'])->toBe('org_1');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
    expect((string) $mock->lastRequest()->getBody())->toBe('{"name":"Acme"}');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $mockA = (new MockTransport)->enqueue(201, ['id' => 'org_a']);
    $mockB = (new MockTransport)->enqueue(201, ['id' => 'org_a']);

    (new OrganizationsManager($mockA->transport()))->create(['name' => 'Acme', 'slug' => 'acme']);
    (new OrganizationsManager($mockB->transport()))->create(['slug' => 'acme', 'name' => 'Acme']);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('gets, updates, deletes an organization', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'org_1', 'name' => 'Acme'])
        ->enqueue(body: ['id' => 'org_1', 'name' => 'Acme Corp'])
        ->enqueue(204);

    $orgs = new OrganizationsManager($mock->transport());

    expect($orgs->get('org_1')['name'])->toBe('Acme');
    expect($orgs->update('org_1', ['name' => 'Acme Corp'])['name'])->toBe('Acme Corp');
    $orgs->delete('org_1');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/organizations/org_1');
});

it('members() returns an OrganizationMembershipsManager bound to the org', function (): void {
    $mock = new MockTransport;
    $orgs = new OrganizationsManager($mock->transport());

    expect($orgs->members('org_1'))->toBeInstanceOf(OrganizationMembershipsManager::class);
});

it('invitations() returns an OrganizationInvitationsManager bound to the org', function (): void {
    $mock = new MockTransport;
    $orgs = new OrganizationsManager($mock->transport());

    expect($orgs->invitations('org_1'))->toBeInstanceOf(OrganizationInvitationsManager::class);
});

it('domains() returns an OrganizationDomainsManager bound to the org', function (): void {
    $mock = new MockTransport;
    $orgs = new OrganizationsManager($mock->transport());

    expect($orgs->domains('org_1'))->toBeInstanceOf(OrganizationDomainsManager::class);
});

it('Client::organizations() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->organizations())->toBeInstanceOf(OrganizationsManager::class);
});
