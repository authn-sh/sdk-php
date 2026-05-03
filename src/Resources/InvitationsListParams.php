<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class InvitationsListParams extends ListParams
{
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        public ?string $status = null,
    ) {
        parent::__construct($limit, $offset, $orderBy);
    }

    /**
     * @return array<string, scalar|null|array<int, scalar|null>>
     */
    public function toQuery(): array
    {
        $q = parent::toQuery();

        if ($this->status !== null) {
            $q['status'] = $this->status;
        }

        return $q;
    }
}
