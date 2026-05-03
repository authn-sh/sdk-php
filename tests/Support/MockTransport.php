<?php

declare(strict_types=1);

namespace Authn\Sdk\Tests\Support;

use Authn\Sdk\Config;
use Authn\Sdk\Http\Transport;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Test helper: a queue-backed PSR-18 client + Transport factory in one.
 *
 * @phpstan-type Headers array<string, string>
 */
final class MockTransport implements ClientInterface
{
    private readonly Psr17Factory $factory;

    /** @var list<ResponseInterface> */
    private array $responses = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function __construct()
    {
        $this->factory = new Psr17Factory;
    }

    /**
     * @param  array<int|string, mixed>|string  $body
     * @param  Headers  $headers
     */
    public function enqueue(int $status = 200, array|string $body = [], array $headers = []): self
    {
        try {
            $payload = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
        } catch (JsonException $e) {
            throw new RuntimeException('MockTransport: failed to encode body: ' . $e->getMessage(), 0, $e);
        }

        $response = $this->factory->createResponse($status);

        if (! isset($headers['Content-Type']) && is_array($body)) {
            $response = $response->withHeader('Content-Type', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        if ($payload !== '') {
            $response = $response->withBody($this->factory->createStream($payload));
        }

        $this->responses[] = $response;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new RuntimeException('MockTransport: no response queued for ' . $request->getMethod() . ' ' . $request->getUri());
        }

        return array_shift($this->responses);
    }

    public function transport(string $secretKey = 'sk_test_123', ?string $apiUrl = null): Transport
    {
        return new Transport(
            config: new Config(secretKey: $secretKey, apiUrl: $apiUrl),
            http: $this,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );
    }

    public function lastRequest(): RequestInterface
    {
        $count = count($this->requests);
        if ($count === 0) {
            throw new RuntimeException('MockTransport: no requests captured');
        }

        return $this->requests[$count - 1];
    }

    public function requestAt(int $index): RequestInterface
    {
        if (! isset($this->requests[$index])) {
            throw new RuntimeException("MockTransport: no request at index {$index}");
        }

        return $this->requests[$index];
    }
}
