<?php

declare(strict_types=1);

use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\UsersListParams;
use Authn\Sdk\Util\Query;

it('emits only set fields', function (): void {
    expect((new ListParams)->toQuery())->toBe([]);
    expect((new ListParams(limit: 10))->toQuery())->toBe(['limit' => 10]);
    expect((new ListParams(offset: 20, orderBy: '-created_at'))->toQuery())
        ->toBe(['offset' => 20, 'order_by' => '-created_at']);
});

it('UsersListParams renders array filters with repeated keys', function (): void {
    $params = new UsersListParams(
        limit: 5,
        emailAddress: ['a@b.com', 'c@d.com'],
        username: ['jane'],
        query: 'jane',
    );

    $qs = Query::build($params->toQuery());

    expect($qs)->toContain('limit=5')
        ->toContain('email_address=a%40b.com')
        ->toContain('email_address=c%40d.com')
        ->toContain('username=jane')
        ->toContain('query=jane');

    expect(substr_count($qs, 'email_address='))->toBe(2);
    expect($qs)->not->toContain('email_address[]');
    expect($qs)->not->toContain('email_address%5B');
});
