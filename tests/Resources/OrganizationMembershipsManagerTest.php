<?php

declare(strict_types=1);

use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\OrganizationMembershipsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists memberships under the correct org path', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'mem_1', 'role' => 'admin']],
        'total_count' => 1,
    ]);
    $manager = new OrganizationMembershipsManager($mock->transport(), 'org_1');

    $list = $manager->list(new ListParams(limit: 5));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/organizations/org_1/memberships');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=5');
});

it('creates a membership with an auto-generated idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'mem_1']);
    $manager = new OrganizationMembershipsManager($mock->transport(), 'org_1');

    $manager->create(['user_id' => 'user_1', 'role' => 'basic_member']);

    $request = $mock->lastRequest();
    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toEndWith('/v1/organizations/org_1/memberships');
    expect($request->getHeaderLine('Idempotency-Key'))->not->toBe('');
});

it('creates a membership with a caller-supplied idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'mem_1']);
    $manager = new OrganizationMembershipsManager($mock->transport(), 'org_1');

    $manager->create(['user_id' => 'user_1', 'role' => 'admin'], idempotencyKey: 'my-key');

    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('my-key');
});

it('updates a membership role via PATCH', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'mem_1', 'role' => 'admin']);
    $manager = new OrganizationMembershipsManager($mock->transport(), 'org_1');

    $result = $manager->update('user_1', 'admin');

    expect($result['role'])->toBe('admin');
    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/organizations/org_1/memberships/user_1');
    expect((string) $mock->lastRequest()->getBody())->toBe('{"role":"admin"}');
});

it('deletes a membership via DELETE', function (): void {
    $mock = (new MockTransport)->enqueue(204);
    $manager = new OrganizationMembershipsManager($mock->transport(), 'org_1');

    $manager->delete('user_1');

    expect($mock->lastRequest()->getMethod())->toBe('DELETE');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/organizations/org_1/memberships/user_1');
});
