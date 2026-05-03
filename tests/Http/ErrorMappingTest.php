<?php

declare(strict_types=1);

use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Http\AuthenticationException;
use Authn\Sdk\Http\RateLimitExceededException;
use Authn\Sdk\Http\ResourceNotFoundException;
use Authn\Sdk\Tests\Support\MockTransport;

it('maps 401 to AuthenticationException', function (): void {
    $mock = (new MockTransport)->enqueue(401, [
        'errors' => [['code' => 'authorization_invalid', 'message' => 'bad key']],
    ]);
    $transport = $mock->transport();

    expect(fn () => $transport->send('GET', '/v1/users'))
        ->toThrow(AuthenticationException::class);
});

it('maps 404 to ResourceNotFoundException', function (): void {
    $mock = (new MockTransport)->enqueue(404, [
        'errors' => [['code' => 'resource_not_found', 'message' => 'gone']],
    ]);
    $transport = $mock->transport();

    try {
        $transport->send('GET', '/v1/users/missing');
        throw new RuntimeException('Expected ResourceNotFoundException');
    } catch (ResourceNotFoundException $e) {
        expect($e->getStatusCode())->toBe(404);
        expect($e->getErrorCode())->toBe('resource_not_found');
    }
});

it('maps 429 to RateLimitExceededException with parsed Retry-After (seconds)', function (): void {
    $mock = (new MockTransport)->enqueue(429, [
        'errors' => [['code' => 'rate_limit_exceeded', 'message' => 'slow down']],
    ], ['Retry-After' => '7']);
    $transport = $mock->transport();

    try {
        $transport->send('GET', '/v1/users');
        throw new RuntimeException('Expected RateLimitExceededException');
    } catch (RateLimitExceededException $e) {
        expect($e->getRetryAfter())->toBe(7);
        expect($e->getErrorCode())->toBe('rate_limit_exceeded');
    }
});

it('parses Retry-After in HTTP-date format', function (): void {
    $future = gmdate('D, d M Y H:i:s \G\M\T', time() + 12);
    $mock = (new MockTransport)->enqueue(429, [], ['Retry-After' => $future]);
    $transport = $mock->transport();

    try {
        $transport->send('GET', '/v1/users');
    } catch (RateLimitExceededException $e) {
        expect($e->getRetryAfter())->toBeGreaterThanOrEqual(10);
        expect($e->getRetryAfter())->toBeLessThanOrEqual(13);
    }
});

it('returns 0 from getRetryAfter when header is missing', function (): void {
    $mock = (new MockTransport)->enqueue(429, []);

    try {
        $mock->transport()->send('GET', '/v1/users');
    } catch (RateLimitExceededException $e) {
        expect($e->getRetryAfter())->toBe(0);
    }
});

it('falls back to ApiException for any other 4xx/5xx', function (): void {
    $mock = (new MockTransport)->enqueue(422, [
        'errors' => [
            ['code' => 'form_param_format_invalid', 'message' => 'bad email'],
            ['code' => 'form_param_missing', 'message' => 'username required'],
        ],
        'trace_id' => 'tr_1',
    ]);

    try {
        $mock->transport()->send('POST', '/v1/users', ['body' => []]);
    } catch (ApiException $e) {
        expect($e instanceof AuthenticationException)->toBeFalse();
        expect($e instanceof ResourceNotFoundException)->toBeFalse();
        expect($e instanceof RateLimitExceededException)->toBeFalse();
        expect($e->getErrorCode())->toBe('form_param_format_invalid');
        expect($e->getErrors())->toHaveCount(2);
        expect($e->getTraceId())->toBe('tr_1');
    }
});

it('getErrorCode returns null when no errors are present', function (): void {
    $mock = (new MockTransport)->enqueue(500, '');

    try {
        $mock->transport()->send('GET', '/v1/users');
    } catch (ApiException $e) {
        expect($e->getErrorCode())->toBeNull();
        expect($e->getStatusCode())->toBe(500);
    }
});
