<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Resources\RolesManager;
use Authn\Sdk\Resources\SystemPermissions;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists roles with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'role_1', 'key' => 'org:admin'], ['id' => 'role_2', 'key' => 'org:member']],
        'total_count' => 2,
    ]);
    $roles = new RolesManager($mock->transport());

    $list = $roles->list(new ListParams(limit: 10));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/roles');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
});

it('creates a role with an idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'role_1', 'key' => 'org:billing_admin']);
    $roles = new RolesManager($mock->transport());

    $created = $roles->create(['name' => 'Billing Admin', 'key' => 'org:billing_admin'], idempotencyKey: 'idem-1');

    expect($created['id'])->toBe('role_1');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
    expect((string) $mock->lastRequest()->getUri())->toBe('https://api.authn.sh/v1/roles');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $mockA = (new MockTransport)->enqueue(201, ['id' => 'role_a']);
    $mockB = (new MockTransport)->enqueue(201, ['id' => 'role_a']);

    (new RolesManager($mockA->transport()))->create(['name' => 'Admin', 'key' => 'org:admin']);
    (new RolesManager($mockB->transport()))->create(['key' => 'org:admin', 'name' => 'Admin']);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('gets, updates, deletes a role', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'role_1', 'name' => 'Admin'])
        ->enqueue(body: ['id' => 'role_1', 'name' => 'Super Admin'])
        ->enqueue(204);

    $roles = new RolesManager($mock->transport());

    expect($roles->get('role_1')['name'])->toBe('Admin');
    expect($roles->update('role_1', ['name' => 'Super Admin'])['name'])->toBe('Super Admin');
    $roles->delete('role_1');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/roles/role_1');
});

it('setPermissions sends PUT with permissions array', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'role_1', 'permissions' => [
        ['key' => SystemPermissions::ORG_SYS_PROFILE_MANAGE],
        ['key' => SystemPermissions::ORG_SYS_MEMBERSHIPS_READ],
    ]]);
    $roles = new RolesManager($mock->transport());

    $result = $roles->setPermissions('role_1', [
        SystemPermissions::ORG_SYS_PROFILE_MANAGE,
        SystemPermissions::ORG_SYS_MEMBERSHIPS_READ,
    ]);

    expect($result['id'])->toBe('role_1');
    expect($mock->lastRequest()->getMethod())->toBe('PUT');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/roles/role_1/permissions');
    expect((string) $mock->lastRequest()->getBody())
        ->toBe('{"permissions":["org:sys_profile:manage","org:sys_memberships:read"]}');
});

it('setPermissions with empty array clears all permissions', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'role_1', 'permissions' => []]);
    $roles = new RolesManager($mock->transport());

    $roles->setPermissions('role_1', []);

    expect((string) $mock->lastRequest()->getBody())->toBe('{"permissions":[]}');
});

it('system-role mutation surfaces as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(422, ['errors' => [['code' => 'system_role_not_editable', 'message' => 'Cannot edit a system role']]]);
    $roles = new RolesManager($mock->transport());

    expect(fn () => $roles->update('role_sys_admin', ['name' => 'x']))->toThrow(ApiException::class);
});

it('Client::roles() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->roles())->toBeInstanceOf(RolesManager::class);
});
