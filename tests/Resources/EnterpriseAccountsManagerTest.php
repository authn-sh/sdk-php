<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\EnterpriseAccount;
use Authn\Sdk\Resources\EnterpriseAccountsListParams;
use Authn\Sdk\Resources\EnterpriseAccountsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function enterpriseAccountPayload(): array
{
    return [
        'id' => 'entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'enterprise_account',
        'enterprise_connection_id' => 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'provider_user_id' => 'alice@acme.example',
        'email_address' => 'alice@acme.example',
        'verified' => true,
        'public_metadata' => [
            'groups' => ['engineering', 'sso-admins'],
            'department' => 'Platform',
        ],
        'linked_at' => 1_714_723_000_000,
        'last_signed_in_at' => 1_714_896_500_000,
        'created_at' => 1_714_723_000_000,
        'updated_at' => 1_714_896_500_000,
    ];
}

it('lists enterprise accounts with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [enterpriseAccountPayload()],
        'total_count' => 1,
    ]);
    $manager = new EnterpriseAccountsManager($mock->transport());

    $list = $manager->list(new EnterpriseAccountsListParams(limit: 10));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/enterprise-accounts');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
});

it('filters list by user_id', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $manager = new EnterpriseAccountsManager($mock->transport());

    $manager->list(new EnterpriseAccountsListParams(userId: 'user_01HKX9SY9V7H7TF8C8K7J9X4ZB'));

    expect((string) $mock->lastRequest()->getUri())
        ->toContain('user_id=user_01HKX9SY9V7H7TF8C8K7J9X4ZB');
});

it('filters list by enterprise_connection_id', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $manager = new EnterpriseAccountsManager($mock->transport());

    $manager->list(new EnterpriseAccountsListParams(
        enterpriseConnectionId: 'entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA',
    ));

    expect((string) $mock->lastRequest()->getUri())
        ->toContain('enterprise_connection_id=entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('gets an enterprise account', function (): void {
    $mock = (new MockTransport)->enqueue(body: enterpriseAccountPayload());
    $manager = new EnterpriseAccountsManager($mock->transport());

    $got = $manager->get('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($got)->toBeInstanceOf(EnterpriseAccount::class);
    expect($got->id)->toBe('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($got->enterpriseConnectionId)->toBe('entcon_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($got->verified)->toBeTrue();
    expect($mock->lastRequest()->getMethod())->toBe('GET');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/enterprise-accounts/entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('deletes an enterprise account', function (): void {
    $mock = (new MockTransport)->enqueue(204);
    $manager = new EnterpriseAccountsManager($mock->transport());

    $manager->delete('entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->lastRequest()->getMethod())->toBe('DELETE');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/enterprise-accounts/entacc_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('not-found surfaces as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(404, [
        'errors' => [['code' => 'enterprise_account_not_found', 'message' => 'not found', 'long_message' => '...']],
    ]);
    $manager = new EnterpriseAccountsManager($mock->transport());

    expect(fn () => $manager->get('entacc_missing'))->toThrow(ApiException::class);
});

it('Client::enterpriseAccounts() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->enterpriseAccounts())->toBeInstanceOf(EnterpriseAccountsManager::class);
});
