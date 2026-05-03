<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Minimal PSR-18 client that captures the last request and replays a queued response.
 */
final class CapturingClient implements ClientInterface
{
    private ?RequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public function lastRequest(): RequestInterface
    {
        if ($this->lastRequest === null) {
            throw new RuntimeException('No request has been captured yet.');
        }

        return $this->lastRequest;
    }
}
