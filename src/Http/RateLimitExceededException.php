<?php

declare(strict_types=1);

namespace Authn\Sdk\Http;

final class RateLimitExceededException extends ApiException
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $errors = [],
        ?string $traceId = null,
        ?string $rawBody = null,
        private readonly int $retryAfter = 0,
    ) {
        parent::__construct($message, $statusCode, $errors, $traceId, $rawBody);
    }

    /**
     * Seconds the caller should wait before retrying. 0 if the server didn't send a Retry-After header.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
