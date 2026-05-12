<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseConnectionTestResult
{
    /**
     * @param  list<EnterpriseConnectionTestError>  $errors
     */
    public function __construct(
        public readonly string $authorizeUrl,
        public readonly ?int $discoveryStatus,
        public readonly array $errors,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $errors = [];
        if (isset($payload['errors']) && is_array($payload['errors'])) {
            foreach ($payload['errors'] as $entry) {
                if (is_array($entry)) {
                    /** @var array<string, mixed> $entry */
                    $errors[] = EnterpriseConnectionTestError::fromPayload($entry);
                }
            }
        }

        $authorizeUrl = isset($payload['authorize_url']) && is_string($payload['authorize_url'])
            ? $payload['authorize_url']
            : '';

        $discoveryStatus = $payload['discovery_status'] ?? null;
        if (! is_int($discoveryStatus)) {
            $discoveryStatus = null;
        }

        return new self(
            authorizeUrl: $authorizeUrl,
            discoveryStatus: $discoveryStatus,
            errors: $errors,
        );
    }

    public function passed(): bool
    {
        return $this->errors === [];
    }
}
