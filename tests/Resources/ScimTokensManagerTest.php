<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\OrganizationsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Resources\ScimToken;
use Authn\Sdk\Resources\ScimTokensManager;
use Authn\Sdk\Resources\ScimTokenWithPlaintext;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function scimTokenPayload(): array
{
    return [
        'id' => 'scimt_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'scim_token',
        'organization_id' => 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB',
        'name' => 'Okta — Production',
        'prefix' => 'scim_01HKX9SY',
        'created_at' => 1_714_723_000_000,
        'revoked_at' => null,
    ];
}

it('lists SCIM tokens for an organization', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [scimTokenPayload()],
        'total_count' => 1,
    ]);

    $manager = new ScimTokensManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');
    $list = $manager->list();

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())
        ->toContain('/v1/organizations/org_01HKX9SY9V7H7TF8C8K7J9X4ZB/scim/tokens');
});

it('issues a fresh SCIM token returning the plaintext exactly once', function (): void {
    $payload = scimTokenPayload();
    $payload['token'] = 'scim_01HKX9SYABCDEFGHJKMNPQRSTVWXYZ234567';

    $mock = (new MockTransport)->enqueue(201, $payload);
    $manager = new ScimTokensManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    $issued = $manager->issue('Okta — Production', idempotencyKey: 'idem-1');

    expect($issued)->toBeInstanceOf(ScimTokenWithPlaintext::class);
    expect($issued->id)->toBe('scimt_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($issued->organizationId)->toBe('org_01HKX9SY9V7H7TF8C8K7J9X4ZB');
    expect($issued->name)->toBe('Okta — Production');
    expect($issued->prefix)->toBe('scim_01HKX9SY');
    expect($issued->token)->toBe('scim_01HKX9SYABCDEFGHJKMNPQRSTVWXYZ234567');
    expect($issued->isRevoked())->toBeFalse();

    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
    expect((string) $mock->lastRequest()->getBody())
        ->toBe('{"name":"Okta — Production"}');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $payload = scimTokenPayload();
    $payload['token'] = 'scim_PLAINTEXT';

    $mockA = (new MockTransport)->enqueue(201, $payload);
    $mockB = (new MockTransport)->enqueue(201, $payload);

    (new ScimTokensManager($mockA->transport(), 'org_x'))->issue('Okta');
    (new ScimTokensManager($mockB->transport(), 'org_x'))->issue('Okta');

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('rejects payloads that omit the plaintext token on issue', function (): void {
    $mock = (new MockTransport)->enqueue(201, scimTokenPayload());
    $manager = new ScimTokensManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    expect(fn () => $manager->issue('Okta'))->toThrow(RuntimeException::class);
});

it('revokes a SCIM token and surfaces the revoked timestamp', function (): void {
    $revoked = scimTokenPayload();
    $revoked['revoked_at'] = 1_714_724_000_000;

    $mock = (new MockTransport)->enqueue(body: $revoked);
    $manager = new ScimTokensManager($mock->transport(), 'org_01HKX9SY9V7H7TF8C8K7J9X4ZB');

    $token = $manager->revoke('scimt_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($token)->toBeInstanceOf(ScimToken::class);
    expect($token->isRevoked())->toBeTrue();
    expect($token->revokedAt)->toBe(1_714_724_000_000);
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/organizations/org_01HKX9SY9V7H7TF8C8K7J9X4ZB/scim/tokens/scimt_01HKX9SY9V7H7TF8C8K7J9X4ZA/revoke');
});

it('surfaces 404 errors from the API as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(404, [
        'errors' => [['code' => 'scim_token_not_found', 'message' => 'not found', 'long_message' => '...']],
    ]);
    $manager = new ScimTokensManager($mock->transport(), 'org_x');

    expect(fn () => $manager->revoke('scimt_missing'))->toThrow(ApiException::class);
});

it('Client::organizations()->scimTokens() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);
    $orgs = $client->organizations();

    expect($orgs)->toBeInstanceOf(OrganizationsManager::class);
    expect($orgs->scimTokens('org_x'))->toBeInstanceOf(ScimTokensManager::class);
});
