<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Resources\Passkey;
use Authn\Sdk\Resources\PasskeysListParams;
use Authn\Sdk\Resources\PasskeysManager;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function yubikeyPasskeyPayload(): array
{
    return [
        'id' => 'pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'passkey',
        'nickname' => 'YubiKey 5C',
        'transports' => ['usb', 'nfc'],
        'aaguid' => 'ee882879-721c-4913-9775-3dfcce97072a',
        'verified' => true,
        'last_used_at' => 1_714_896_500_000,
        'created_at' => 1_714_723_000_000,
        'updated_at' => 1_714_896_500_000,
    ];
}

/**
 * @return array<string, mixed>
 */
function touchIdPasskeyPayload(): array
{
    return [
        'id' => 'pkey_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'object' => 'passkey',
        'nickname' => 'MacBook Touch ID',
        'transports' => ['internal', 'hybrid'],
        'aaguid' => null,
        'verified' => true,
        'last_used_at' => null,
        'created_at' => 1_714_724_000_000,
        'updated_at' => 1_714_724_000_000,
    ];
}

it('lists passkeys with pagination and a user_id filter', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [yubikeyPasskeyPayload(), touchIdPasskeyPayload()],
        'total_count' => 2,
    ]);
    $passkeys = new PasskeysManager($mock->transport());

    $list = $passkeys->list(new PasskeysListParams(limit: 10, userId: 'user_01HKX9SY9V7H7TF8C8K7J9X4ZZ'));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/passkeys');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
    expect((string) $mock->lastRequest()->getUri())
        ->toContain('user_id=user_01HKX9SY9V7H7TF8C8K7J9X4ZZ');
});

it('gets, renames, deletes a passkey', function (): void {
    $payload = yubikeyPasskeyPayload();
    $renamed = ['nickname' => 'Work YubiKey'] + $payload;

    $mock = (new MockTransport)
        ->enqueue(body: $payload)
        ->enqueue(body: $renamed)
        ->enqueue(204);

    $passkeys = new PasskeysManager($mock->transport());

    $got = $passkeys->get('pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($got)->toBeInstanceOf(Passkey::class);
    expect($got->nickname)->toBe('YubiKey 5C');
    expect($got->transports)->toBe(['usb', 'nfc']);
    expect($got->aaguid)->toBe('ee882879-721c-4913-9775-3dfcce97072a');
    expect($got->verified)->toBeTrue();
    expect($got->lastUsedAt)->toBe(1_714_896_500_000);

    $updated = $passkeys->update('pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA', 'Work YubiKey');
    expect($updated->nickname)->toBe('Work YubiKey');

    $passkeys->delete('pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())
        ->toEndWith('/v1/passkeys/pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('parses platform passkey payload with null aaguid and null last_used_at', function (): void {
    $mock = (new MockTransport)->enqueue(body: touchIdPasskeyPayload());
    $passkeys = new PasskeysManager($mock->transport());

    $got = $passkeys->get('pkey_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    expect($got->aaguid)->toBeNull();
    expect($got->lastUsedAt)->toBeNull();
    expect($got->transports)->toBe(['internal', 'hybrid']);
});

it('passes an idempotency key on rename when provided', function (): void {
    $mock = (new MockTransport)->enqueue(body: yubikeyPasskeyPayload());
    $passkeys = new PasskeysManager($mock->transport());

    $passkeys->update('pkey_01HKX9SY9V7H7TF8C8K7J9X4ZA', 'YubiKey 5C', idempotencyKey: 'idem-1');

    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
});

it('surfaces server errors as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(404, [
        'errors' => [['code' => 'passkey_not_found', 'message' => 'not found', 'long_message' => '...']],
    ]);
    $passkeys = new PasskeysManager($mock->transport());

    expect(fn () => $passkeys->get('pkey_missing'))->toThrow(ApiException::class);
});

it('Client::passkeys() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->passkeys())->toBeInstanceOf(PasskeysManager::class);
});
