<?php

declare(strict_types=1);

use Authn\Sdk\Config;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Http\NetworkException;
use Authn\Sdk\Http\Transport;
use Authn\Sdk\Tests\Support\CapturingClient;
use Authn\Sdk\Tests\Support\ThrowingClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

function makeTransport(ClientInterface $http, ?string $apiUrl = null): Transport
{
    $factory = new Psr17Factory;

    return new Transport(
        config: new Config(secretKey: 'sk_test_123', apiUrl: $apiUrl),
        http: $http,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

it('sends the expected headers on a GET request', function (): void {
    $factory = new Psr17Factory;
    $client = new CapturingClient($factory->createResponse(200)
        ->withHeader('Content-Type', 'application/json')
        ->withBody($factory->createStream('{"id":"user_1","object":"user"}')));

    $transport = makeTransport($client);
    $result = $transport->send('GET', '/v1/users/user_1');

    expect($result)->toBe(['id' => 'user_1', 'object' => 'user']);

    $request = $client->lastRequest();
    expect($request->getMethod())->toBe('GET');
    expect((string) $request->getUri())->toBe('https://api.authn.sh/v1/users/user_1');
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer sk_test_123');
    expect($request->getHeaderLine('Accept'))->toBe('application/json');
    expect($request->getHeaderLine('User-Agent'))->toStartWith('authn-sdk-php/');
    expect($request->getHeaderLine('X-Authn-Request-Id'))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    expect($request->hasHeader('Content-Type'))->toBeFalse();
    expect($request->hasHeader('Idempotency-Key'))->toBeFalse();
});

it('encodes the body as JSON and sets Content-Type on POST', function (): void {
    $factory = new Psr17Factory;
    $client = new CapturingClient($factory->createResponse(201)
        ->withBody($factory->createStream('{"id":"user_2"}')));

    $transport = makeTransport($client);
    $transport->send('POST', '/v1/users', [
        'body' => ['email_address' => 'a@b.com'],
        'idempotencyKey' => 'idem-abc',
    ]);

    $request = $client->lastRequest();
    expect($request->getMethod())->toBe('POST');
    expect($request->getHeaderLine('Content-Type'))->toBe('application/json');
    expect($request->getHeaderLine('Idempotency-Key'))->toBe('idem-abc');
    expect((string) $request->getBody())->toBe('{"email_address":"a@b.com"}');
});

it('appends query parameters to the URI', function (): void {
    $factory = new Psr17Factory;
    $client = new CapturingClient($factory->createResponse(200)
        ->withBody($factory->createStream('{"data":[],"total_count":0}')));

    $transport = makeTransport($client);
    $transport->send('GET', '/v1/users', [
        'query' => ['limit' => 10, 'order_by' => '-created_at'],
    ]);

    expect((string) $client->lastRequest()->getUri())
        ->toBe('https://api.authn.sh/v1/users?limit=10&order_by=-created_at');
});

it('returns an empty array on 204 No Content', function (): void {
    $factory = new Psr17Factory;
    $client = new CapturingClient($factory->createResponse(204));

    $transport = makeTransport($client);
    expect($transport->send('DELETE', '/v1/users/user_1'))->toBe([]);
});

it('parses errors[] and trace_id into ApiException on 422', function (): void {
    $factory = new Psr17Factory;
    $payload = json_encode([
        'errors' => [
            [
                'code' => 'form_param_format_invalid',
                'message' => 'email_address must be a valid email',
                'long_message' => 'The provided email_address is not formatted correctly.',
                'meta' => ['param_name' => 'email_address'],
            ],
        ],
        'trace_id' => 'trace_abc',
    ], JSON_THROW_ON_ERROR);

    $client = new CapturingClient($factory->createResponse(422)
        ->withHeader('Content-Type', 'application/json')
        ->withBody($factory->createStream($payload)));

    $transport = makeTransport($client);

    expect(fn () => $transport->send('POST', '/v1/users', ['body' => ['email_address' => 'nope']]))
        ->toThrow(ApiException::class);

    try {
        $transport->send('POST', '/v1/users', ['body' => ['email_address' => 'nope']]);
        throw new RuntimeException('Expected ApiException was not thrown.');
    } catch (ApiException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->getTraceId())->toBe('trace_abc');
        expect($e->getMessage())->toBe('email_address must be a valid email');
        expect($e->getErrors())->toHaveCount(1);
        expect($e->getErrors()[0]['code'])->toBe('form_param_format_invalid');
        expect($e->getRawBody())->toBe($payload);
    }
});

it('wraps PSR-18 transport errors in NetworkException', function (): void {
    $transport = makeTransport(new ThrowingClient);

    expect(fn () => $transport->send('GET', '/v1/users'))
        ->toThrow(NetworkException::class);
});

it('honors a custom apiUrl and trims trailing slash', function (): void {
    $factory = new Psr17Factory;
    $client = new CapturingClient($factory->createResponse(200)->withBody($factory->createStream('{}')));

    $transport = makeTransport($client, 'https://staging.authn.sh/');
    $transport->send('GET', '/v1/users');

    expect((string) $client->lastRequest()->getUri())->toBe('https://staging.authn.sh/v1/users');
});
