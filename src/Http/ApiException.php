<?php

declare(strict_types=1);

namespace Authn\Sdk\Http;

class ApiException extends Exception
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

    public function getErrorCode(): ?string
    {
        $first = $this->errors[0] ?? null;

        if (! is_array($first)) {
            return null;
        }

        $code = $first['code'] ?? null;

        return is_string($code) ? $code : null;
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
