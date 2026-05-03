<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tiny PSR-18 client that returns a queued body for a queued status, counting calls.
 *
 * Built specifically for JWKS-fetch tests where we need to count how many times
 * the SDK hit the JWKS endpoint and swap the response between calls.
 */
final class StaticBodyClient implements ClientInterface
{
    public int $calls = 0;

    /** @var list<string|null> */
    public array $bodies;

    public function __construct(?string ...$bodies)
    {
        $this->bodies = $bodies === [] ? [null] : array_values($bodies);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $idx = min($this->calls, count($this->bodies) - 1);
        $body = $this->bodies[$idx];
        $this->calls++;

        $factory = new Psr17Factory;
        $response = $factory->createResponse($body === null ? 500 : 200)
            ->withHeader('Content-Type', 'application/json');
        if ($body !== null) {
            $response = $response->withBody($factory->createStream($body));
        }

        return $response;
    }
}
