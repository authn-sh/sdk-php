<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class SmsTemplate
{
    public const SLUG_VERIFICATION_CODE = 'verification_code';

    public const SLUG_RESET_PASSWORD_CODE = 'reset_password_code';

    public const SLUG_INVITATION = 'invitation';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $body,
        public readonly bool $deliveredByUs,
        public readonly ?string $fromNumberOverride,
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
            slug: isset($payload['slug']) && is_string($payload['slug']) ? $payload['slug'] : '',
            body: isset($payload['body']) && is_string($payload['body']) ? $payload['body'] : '',
            deliveredByUs: (bool) ($payload['delivered_by_us'] ?? true),
            fromNumberOverride: isset($payload['from_number_override']) && is_string($payload['from_number_override'])
                ? $payload['from_number_override']
                : null,
            createdAt: isset($payload['created_at']) && is_int($payload['created_at']) ? $payload['created_at'] : 0,
            updatedAt: isset($payload['updated_at']) && is_int($payload['updated_at']) ? $payload['updated_at'] : 0,
            raw: $payload,
        );
    }
}
