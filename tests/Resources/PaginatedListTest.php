<?php

declare(strict_types=1);

use Authn\Sdk\Resources\PaginatedList;

it('iterates and counts the data array', function (): void {
    $list = PaginatedList::fromResponse([
        'data' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']],
        'total_count' => 12,
    ]);

    expect($list)->toHaveCount(3);
    expect($list->totalCount)->toBe(12);
    expect(iterator_to_array($list))->toBe([
        ['id' => 'a'], ['id' => 'b'], ['id' => 'c'],
    ]);
});

it('falls back to len(data) when total_count is missing', function (): void {
    $list = PaginatedList::fromResponse(['data' => [['id' => 'a']]]);

    expect($list->totalCount)->toBe(1);
});

it('exposes an empty constructor', function (): void {
    $list = PaginatedList::empty();

    expect($list->totalCount)->toBe(0);
    expect($list)->toHaveCount(0);
});
