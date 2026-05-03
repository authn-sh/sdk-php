<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class UsersListParams extends ListParams
{
    /**
     * @param  list<string>  $emailAddress
     * @param  list<string>  $phoneNumber
     * @param  list<string>  $username
     * @param  list<string>  $userId
     * @param  list<string>  $externalId
     */
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $orderBy = null,
        public array $emailAddress = [],
        public array $phoneNumber = [],
        public array $username = [],
        public array $userId = [],
        public array $externalId = [],
        public ?string $query = null,
        public ?int $lastActiveAtSince = null,
        public ?int $createdAtAfter = null,
        public ?int $createdAtBefore = null,
    ) {
        parent::__construct($limit, $offset, $orderBy);
    }

    /**
     * @return array<string, scalar|null|array<int, scalar|null>>
     */
    public function toQuery(): array
    {
        $q = parent::toQuery();

        if ($this->emailAddress !== []) {
            $q['email_address'] = $this->emailAddress;
        }
        if ($this->phoneNumber !== []) {
            $q['phone_number'] = $this->phoneNumber;
        }
        if ($this->username !== []) {
            $q['username'] = $this->username;
        }
        if ($this->userId !== []) {
            $q['user_id'] = $this->userId;
        }
        if ($this->externalId !== []) {
            $q['external_id'] = $this->externalId;
        }
        if ($this->query !== null) {
            $q['query'] = $this->query;
        }
        if ($this->lastActiveAtSince !== null) {
            $q['last_active_at_since'] = $this->lastActiveAtSince;
        }
        if ($this->createdAtAfter !== null) {
            $q['created_at_after'] = $this->createdAtAfter;
        }
        if ($this->createdAtBefore !== null) {
            $q['created_at_before'] = $this->createdAtBefore;
        }

        return $q;
    }
}
