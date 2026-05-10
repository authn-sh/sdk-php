<?php

declare(strict_types=1);

use Authn\Sdk\Resources\OrganizationDomainsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists domains under the correct org path', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'domain_1', 'name' => 'example.com']],
        'total_count' => 1,
    ]);
    $manager = new OrganizationDomainsManager($mock->transport(), 'org_1');

    $list = $manager->list();

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/organizations/org_1/domains');
});

it('creates a domain with default enrollment mode', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'domain_1', 'name' => 'example.com']);
    $manager = new OrganizationDomainsManager($mock->transport(), 'org_1');

    $result = $manager->create('example.com');

    expect($result['name'])->toBe('example.com');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/organizations/org_1/domains');
    expect((string) $mock->lastRequest()->getBody())
        ->toBe('{"name":"example.com","enrollment_mode":"manual_invitation"}');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->not->toBe('');
});

it('creates a domain with a custom enrollment mode', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'domain_1']);
    $manager = new OrganizationDomainsManager($mock->transport(), 'org_1');

    $manager->create('example.com', 'automatic_invitation');

    expect((string) $mock->lastRequest()->getBody())
        ->toBe('{"name":"example.com","enrollment_mode":"automatic_invitation"}');
});

it('gets, updates, deletes a domain', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'domain_1', 'name' => 'example.com'])
        ->enqueue(body: ['id' => 'domain_1', 'enrollment_mode' => 'automatic_suggestion'])
        ->enqueue(204);

    $manager = new OrganizationDomainsManager($mock->transport(), 'org_1');

    expect($manager->get('domain_1')['name'])->toBe('example.com');
    $manager->update('domain_1', ['enrollment_mode' => 'automatic_suggestion']);
    $manager->delete('domain_1');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/organizations/org_1/domains/domain_1');
    expect((string) $mock->requestAt(1)->getUri())->toEndWith('/v1/organizations/org_1/domains/domain_1');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/organizations/org_1/domains/domain_1');
});
