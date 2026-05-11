<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class ExternalAccountsListParams extends ListParams
{
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        public ?string $userId = null,
        public ?string $oauthProviderId = null,
    ) {
        parent::__construct($limit, $offset, $orderBy);
    }

    /**
     * @return array<string, scalar|null|array<int, scalar|null>>
     */
    public function toQuery(): array
    {
        $q = parent::toQuery();
        if ($this->userId !== null) {
            $q['user_id'] = $this->userId;
        }
        if ($this->oauthProviderId !== null) {
            $q['oauth_provider_id'] = $this->oauthProviderId;
        }

        return $q;
    }
}
