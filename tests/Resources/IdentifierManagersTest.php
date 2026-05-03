<?php

declare(strict_types=1);

use Authn\Sdk\Resources\AllowlistIdentifiersManager;
use Authn\Sdk\Resources\BlocklistIdentifiersManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('Allowlist: list / create / delete', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['data' => [], 'total_count' => 0])
        ->enqueue(201, ['id' => 'allow_1', 'identifier' => 'a@b.com'])
        ->enqueue(204);

    $m = new AllowlistIdentifiersManager($mock->transport());
    $m->list();
    $m->create(['identifier' => 'a@b.com']);
    $m->delete('allow_1');

    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/allowlist_identifiers');
    expect($mock->requestAt(1)->getMethod())->toBe('POST');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/allowlist_identifiers/allow_1');
});

it('Blocklist: list / create / delete', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['data' => [], 'total_count' => 0])
        ->enqueue(201, ['id' => 'block_1', 'identifier' => 'a@b.com'])
        ->enqueue(204);

    $m = new BlocklistIdentifiersManager($mock->transport());
    $m->list();
    $m->create(['identifier' => 'a@b.com']);
    $m->delete('block_1');

    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/blocklist_identifiers');
    expect($mock->requestAt(1)->getMethod())->toBe('POST');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/blocklist_identifiers/block_1');
});
