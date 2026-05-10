<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class OauthProviderTestResult
{
    /**
     * @param  list<OauthProviderTestError>  $errors
     */
    public function __construct(
        public readonly string $authorizeUrl,
        public readonly ?int $userinfoStatus,
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
                    $errors[] = OauthProviderTestError::fromPayload($entry);
                }
            }
        }

        $authorizeUrl = isset($payload['authorize_url']) && is_string($payload['authorize_url'])
            ? $payload['authorize_url']
            : '';

        $userinfoStatus = $payload['userinfo_status'] ?? null;
        if (! is_int($userinfoStatus)) {
            $userinfoStatus = null;
        }

        return new self(
            authorizeUrl: $authorizeUrl,
            userinfoStatus: $userinfoStatus,
            errors: $errors,
        );
    }

    public function passed(): bool
    {
        return $this->errors === [];
    }
}
