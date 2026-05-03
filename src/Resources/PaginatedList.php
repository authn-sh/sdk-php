<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template-implements IteratorAggregate<int, array<string, mixed>>
 */
final class PaginatedList implements Countable, IteratorAggregate
{
    /**
     * @param  list<array<string, mixed>>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $totalCount,
    ) {}

    /**
     * @param  array<int|string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        /** @var list<array<string, mixed>> $data */
        $data = [];
        if (isset($payload['data']) && is_array($payload['data'])) {
            foreach ($payload['data'] as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $data[] = $item;
                }
            }
        }

        $totalCount = isset($payload['total_count']) && is_int($payload['total_count'])
            ? $payload['total_count']
            : count($data);

        return new self($data, $totalCount);
    }

    public static function empty(): self
    {
        return new self([], 0);
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
