<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class TotpVerificationResult
{
    public function __construct(
        public readonly bool $verified,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            verified: isset($payload['verified']) && $payload['verified'] === true,
        );
    }
}
