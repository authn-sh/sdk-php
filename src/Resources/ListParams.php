<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

class ListParams
{
    public function __construct(
        public ?int $limit = null,
        public ?int $offset = null,
        public ?string $orderBy = null,
    ) {}

    /**
     * @return array<string, scalar|null|array<int, scalar|null>>
     */
    public function toQuery(): array
    {
        $q = [];

        if ($this->limit !== null) {
            $q['limit'] = $this->limit;
        }
        if ($this->offset !== null) {
            $q['offset'] = $this->offset;
        }
        if ($this->orderBy !== null) {
            $q['order_by'] = $this->orderBy;
        }

        return $q;
    }
}
