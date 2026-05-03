<?php

declare(strict_types=1);

namespace Authn\Sdk\Http;

use Authn\Sdk\Config;
use Authn\Sdk\Util\Json;
use Authn\Sdk\Util\Uuid;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PSR-18 wrapper that knows how to talk to authn.sh BAPI.
 *
 * @phpstan-type Options array{
 *     query?: array<string, scalar|null|array<int, scalar|null>>,
 *     body?: array<int|string, mixed>,
 *     headers?: array<string, string>,
 *     idempotencyKey?: string|null,
 * }
 */
final class Transport
{
    private readonly ClientInterface $http;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->http = $http ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * @param  Options  $options
     * @return array<int|string, mixed>
     *
     * @throws ApiException
     * @throws NetworkException
     */
    public function send(string $method, string $path, array $options = []): array
    {
        $method = strtoupper($method);
        $uri = $this->buildUri($path, $options['query'] ?? []);

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Authorization', 'Bearer ' . $this->config->secretKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->userAgent())
            ->withHeader('X-Authn-Request-Id', Uuid::v4());

        if (! empty($options['idempotencyKey'])) {
            $request = $request->withHeader('Idempotency-Key', $options['idempotencyKey']);
        }

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if (isset($options['body'])) {
            try {
                $payload = Json::encode($options['body']);
            } catch (JsonException $e) {
                throw new Exception('Failed to encode request body: ' . $e->getMessage(), 0, $e);
            }
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($payload));
        }

        $startedAt = microtime(true);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('authn.sh request failed', [
                'method' => $method,
                'uri' => (string) $uri,
                'error' => $e->getMessage(),
            ]);
            throw new NetworkException('Network error contacting authn.sh: ' . $e->getMessage(), 0, $e);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->getStatusCode();

        $this->logger->info('authn.sh request', [
            'method' => $method,
            'uri' => (string) $uri,
            'status' => $status,
            'duration_ms' => $durationMs,
        ]);

        if ($status >= 400) {
            throw $this->buildApiException($response);
        }

        if ($status === 204) {
            return [];
        }

        return Json::decode((string) $response->getBody());
    }

    /**
     * @param  array<string, scalar|null|array<int, scalar|null>>  $query
     */
    private function buildUri(string $path, array $query): string
    {
        $base = rtrim($this->config->apiUrl, '/');
        $path = '/' . ltrim($path, '/');
        $uri = $base . $path;

        if ($query === []) {
            return $uri;
        }

        return $uri . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function buildApiException(ResponseInterface $response): ApiException
    {
        $rawBody = (string) $response->getBody();
        $decoded = Json::decode($rawBody);

        /** @var list<array<string, mixed>> $errors */
        $errors = [];
        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            foreach ($decoded['errors'] as $error) {
                if (is_array($error)) {
                    /** @var array<string, mixed> $error */
                    $errors[] = $error;
                }
            }
        }

        $traceId = isset($decoded['trace_id']) && is_string($decoded['trace_id'])
            ? $decoded['trace_id']
            : null;

        $message = $errors !== [] && isset($errors[0]['message']) && is_string($errors[0]['message'])
            ? $errors[0]['message']
            : sprintf('authn.sh API request failed with HTTP %d', $response->getStatusCode());

        return new ApiException(
            message: $message,
            statusCode: $response->getStatusCode(),
            errors: $errors,
            traceId: $traceId,
            rawBody: $rawBody,
        );
    }
}
