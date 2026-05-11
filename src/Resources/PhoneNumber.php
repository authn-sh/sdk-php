<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class PhoneNumber
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $phoneNumber,
        public readonly bool $verified,
        public readonly ?string $currentChallengeId,
        public readonly bool $isPrimary,
        public readonly bool $reservedForSecondFactor,
        public readonly bool $defaultSecondFactor,
        public readonly ?string $linkedToExternalAccountId,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '',
            phoneNumber: isset($payload['phone_number']) && is_string($payload['phone_number']) ? $payload['phone_number'] : '',
            verified: (bool) ($payload['verified'] ?? false),
            currentChallengeId: isset($payload['current_challenge_id']) && is_string($payload['current_challenge_id'])
                ? $payload['current_challenge_id']
                : null,
            isPrimary: (bool) ($payload['is_primary'] ?? false),
            reservedForSecondFactor: (bool) ($payload['reserved_for_second_factor'] ?? false),
            defaultSecondFactor: (bool) ($payload['default_second_factor'] ?? false),
            linkedToExternalAccountId: isset($payload['linked_to_external_account_id']) && is_string($payload['linked_to_external_account_id'])
                ? $payload['linked_to_external_account_id']
                : null,
            createdAt: isset($payload['created_at']) && is_int($payload['created_at']) ? $payload['created_at'] : 0,
            updatedAt: isset($payload['updated_at']) && is_int($payload['updated_at']) ? $payload['updated_at'] : 0,
            raw: $payload,
        );
    }
}
