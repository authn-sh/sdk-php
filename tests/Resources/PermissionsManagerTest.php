<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Resources\PermissionsManager;
use Authn\Sdk\Resources\SystemPermissions;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists permissions with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [
            ['key' => SystemPermissions::ORG_SYS_PROFILE_MANAGE, 'is_system' => true],
            ['key' => SystemPermissions::ORG_SYS_MEMBERSHIPS_READ, 'is_system' => true],
        ],
        'total_count' => 2,
    ]);
    $perms = new PermissionsManager($mock->transport());

    $list = $perms->list(new ListParams(limit: 20));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect($mock->lastRequest()->getMethod())->toBe('GET');
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/permissions');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=20');
});

it('lists all permissions without params', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $perms = new PermissionsManager($mock->transport());

    $perms->list();

    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/permissions');
});

it('Client::permissions() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->permissions())->toBeInstanceOf(PermissionsManager::class);
});
