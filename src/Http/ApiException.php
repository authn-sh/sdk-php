<?php

declare(strict_types=1);

namespace Authn\Sdk\Http;

final class ApiException extends Exception
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $errors = [],
        private readonly ?string $traceId = null,
        private readonly ?string $rawBody = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }
}
