<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\OauthApplication;
use Authn\Sdk\Resources\OauthApplicationsManager;
use Authn\Sdk\Resources\OauthApplicationWithSecret;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function oauthApplicationPayload(bool $isPublic = false): array
{
    return [
        'id' => 'oac_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'oauth_application',
        'name' => 'Acme Dashboard',
        'client_id' => 'oac_pub_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'callback_urls' => ['https://app.acme.example/oauth/callback'],
        'scopes' => ['openid', 'profile', 'email'],
        'is_public' => $isPublic,
        'created_at' => 1_714_723_000_000,
        'updated_at' => 1_714_723_000_000,
    ];
}

it('lists OAuth applications', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [oauthApplicationPayload(), oauthApplicationPayload(isPublic: true)],
        'total_count' => 2,
    ]);
    $manager = new OauthApplicationsManager($mock->transport());

    $list = $manager->list();

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/oauth-applications');
});

it('creates a confidential app and returns the plaintext secret exactly once', function (): void {
    $payload = oauthApplicationPayload();
    $payload['client_secret'] = 'osec_01HKX9SYABCDEFGHJKMNPQRSTVWXYZ234567';

    $mock = (new MockTransport)->enqueue(201, $payload);
    $manager = new OauthApplicationsManager($mock->transport());

    $created = $manager->create([
        'name' => 'Acme Dashboard',
        'callback_urls' => ['https://app.acme.example/oauth/callback'],
        'scopes' => ['openid', 'profile', 'email'],
    ], idempotencyKey: 'idem-1');

    expect($created)->toBeInstanceOf(OauthApplicationWithSecret::class);
    expect($created)->toBeInstanceOf(OauthApplication::class);
    /** @var OauthApplicationWithSecret $created */
    expect($created->clientSecret)->toBe('osec_01HKX9SYABCDEFGHJKMNPQRSTVWXYZ234567');
    expect($created->clientId)->toBe('oac_pub_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($created->isConfidential())->toBeTrue();
    expect($created->isPublic)->toBeFalse();
    expect($created->scopes)->toBe(['openid', 'profile', 'email']);
    expect($created->callbackUrls)->toBe(['https://app.acme.example/oauth/callback']);

    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
});

it('creates a public app with no client_secret in the response', function (): void {
    $mock = (new MockTransport)->enqueue(201, oauthApplicationPayload(isPublic: true));
    $manager = new OauthApplicationsManager($mock->transport());

    $created = $manager->create([
        'name' => 'Acme Mobile',
        'callback_urls' => ['com.acme.app://oauth/callback'],
        'is_public' => true,
    ]);

    expect($created)->toBeInstanceOf(OauthApplication::class);
    expect($created)->not->toBeInstanceOf(OauthApplicationWithSecret::class);
    expect($created->isPublic)->toBeTrue();
    expect($created->isConfidential())->toBeFalse();
});

it('auto-generates a stable idempotency key when caller omits one on create', function (): void {
    $payload = oauthApplicationPayload();
    $payload['client_secret'] = 'osec_X';

    $mockA = (new MockTransport)->enqueue(201, $payload);
    $mockB = (new MockTransport)->enqueue(201, $payload);

    (new OauthApplicationsManager($mockA->transport()))->create([
        'name' => 'Acme',
        'callback_urls' => ['https://acme.example/cb'],
    ]);
    (new OauthApplicationsManager($mockB->transport()))->create([
        'callback_urls' => ['https://acme.example/cb'],
        'name' => 'Acme',
    ]);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('gets a single OAuth application without exposing client_secret on read', function (): void {
    $mock = (new MockTransport)->enqueue(body: oauthApplicationPayload());
    $manager = new OauthApplicationsManager($mock->transport());

    $app = $manager->get('oac_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($app)->toBeInstanceOf(OauthApplication::class);
    expect($app)->not->toBeInstanceOf(OauthApplicationWithSecret::class);
    expect($app->raw)->not->toHaveKey('client_secret');
    expect($mock->lastRequest()->getMethod())->toBe('GET');
});

it('updates an OAuth application via PATCH', function (): void {
    $renamed = oauthApplicationPayload();
    $renamed['name'] = 'Acme Dashboard (renamed)';

    $mock = (new MockTransport)->enqueue(body: $renamed);
    $manager = new OauthApplicationsManager($mock->transport());

    $app = $manager->update('oac_01HKX9SY9V7H7TF8C8K7J9X4ZA', ['name' => 'Acme Dashboard (renamed)']);

    expect($app->name)->toBe('Acme Dashboard (renamed)');
    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
});

it('deletes an OAuth application', function (): void {
    $mock = (new MockTransport)->enqueue(204);
    $manager = new OauthApplicationsManager($mock->transport());

    $manager->delete('oac_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->lastRequest()->getMethod())->toBe('DELETE');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/oauth-applications/oac_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('rotates the client secret and returns the new plaintext', function (): void {
    $rotated = oauthApplicationPayload();
    $rotated['client_secret'] = 'osec_01HKX9TTABCDEFGHJKMNPQRSTVWXYZ234567';

    $mock = (new MockTransport)->enqueue(body: $rotated);
    $manager = new OauthApplicationsManager($mock->transport());

    $app = $manager->rotateSecret('oac_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($app)->toBeInstanceOf(OauthApplicationWithSecret::class);
    expect($app->clientSecret)->toBe('osec_01HKX9TTABCDEFGHJKMNPQRSTVWXYZ234567');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/oauth-applications/oac_01HKX9SY9V7H7TF8C8K7J9X4ZA/rotate-secret');
});

it('rejects rotate-secret responses that omit the plaintext', function (): void {
    $mock = (new MockTransport)->enqueue(body: oauthApplicationPayload());
    $manager = new OauthApplicationsManager($mock->transport());

    expect(fn () => $manager->rotateSecret('oac_01HKX9SY9V7H7TF8C8K7J9X4ZA'))
        ->toThrow(RuntimeException::class);
});

it('surfaces 409 oauth_application_public_client when rotating a public client', function (): void {
    $mock = (new MockTransport)->enqueue(409, [
        'errors' => [[
            'code' => 'oauth_application_public_client',
            'message' => 'public clients have no secret to rotate',
            'long_message' => '...',
        ]],
    ]);
    $manager = new OauthApplicationsManager($mock->transport());

    expect(fn () => $manager->rotateSecret('oac_public'))
        ->toThrow(ApiException::class);
});

it('Client::oauthApplications() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->oauthApplications())->toBeInstanceOf(OauthApplicationsManager::class);
});
