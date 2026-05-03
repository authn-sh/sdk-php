<?php

declare(strict_types=1);

namespace Authn\Sdk\Http;

use Authn\Sdk\Config;
use Authn\Sdk\Util\Json;
use Authn\Sdk\Util\Query;
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
 *     rawBody?: string,
 *     contentType?: string,
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
     * @return array<string, mixed>
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

        if (isset($options['body'])) {
            try {
                $payload = Json::encode($options['body']);
            } catch (JsonException $e) {
                throw new Exception('Failed to encode request body: ' . $e->getMessage(), 0, $e);
            }
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($payload));
        } elseif (isset($options['rawBody'])) {
            $contentType = $options['contentType'] ?? 'application/octet-stream';
            $request = $request
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streamFactory->createStream($options['rawBody']));
        }

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        $startedAt = microtime(true);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('authn.sh request failed', [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);
            throw new NetworkException('Network error contacting authn.sh: ' . $e->getMessage(), 0, $e);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->getStatusCode();

        $this->logger->info('authn.sh request', [
            'method' => $method,
            'uri' => $uri,
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

        $queryString = Query::build($query);
        if ($queryString === '') {
            return $uri;
        }

        return $uri . '?' . $queryString;
    }

    private function buildApiException(ResponseInterface $response): ApiException
    {
        $status = $response->getStatusCode();
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
            : sprintf('authn.sh API request failed with HTTP %d', $status);

        return match ($status) {
            401 => new AuthenticationException($message, $status, $errors, $traceId, $rawBody),
            404 => new ResourceNotFoundException($message, $status, $errors, $traceId, $rawBody),
            429 => new RateLimitExceededException(
                $message,
                $status,
                $errors,
                $traceId,
                $rawBody,
                $this->parseRetryAfter($response),
            ),
            default => new ApiException($message, $status, $errors, $traceId, $rawBody),
        };
    }

    private function parseRetryAfter(ResponseInterface $response): int
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header === '') {
            return 0;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);
        if ($timestamp === false) {
            return 0;
        }

        return max(0, $timestamp - time());
    }
}
