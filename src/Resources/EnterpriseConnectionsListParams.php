<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseConnectionsListParams extends ListParams
{
    /**
     * @param  string|null  $organizationId  `Organization.id` to filter scoped rows,
     *                                       the literal string `"null"` to return only
     *                                       instance-wide rows, or `null` to return both.
     */
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        public ?string $organizationId = null,
    ) {
        parent::__construct($limit, $offset, $orderBy);
    }

    /**
     * @return array<string, scalar|null|array<int, scalar|null>>
     */
    public function toQuery(): array
    {
        $q = parent::toQuery();
        if ($this->organizationId !== null) {
            $q['organization_id'] = $this->organizationId;
        }

        return $q;
    }
}
