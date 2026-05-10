<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\OauthProvider;
use Authn\Sdk\Resources\OauthProvidersListParams;
use Authn\Sdk\Resources\OauthProvidersManager;
use Authn\Sdk\Resources\OauthProviderTestResult;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function googlePresetPayload(): array
{
    return [
        'id' => 'oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'oauth_provider',
        'provider_kind' => 'preset',
        'provider_key' => 'google',
        'name' => 'Google',
        'enabled' => true,
        'allow_sign_in' => true,
        'allow_sign_up' => true,
        'block_email_subaddresses' => false,
        'client_id' => '123-abc.apps.googleusercontent.com',
        'scopes' => ['openid', 'email', 'profile'],
        'additional_authorization_params' => ['prompt' => 'select_account'],
        'attribute_mapping' => [
            'email_address' => 'email',
            'first_name' => 'given_name',
            'last_name' => 'family_name',
            'profile_image_url' => 'picture',
            'provider_user_id' => 'sub',
        ],
        'redirect_uri' => 'https://acme.authn.sh/v1/oauth-callback/google',
        'issuer' => 'https://accounts.google.com',
        'discovery_endpoint' => 'https://accounts.google.com/.well-known/openid-configuration',
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
        'id_token_signing_algs' => ['RS256'],
        'userinfo_method' => null,
        'userinfo_auth' => null,
        'created_at' => 1_700_000_000_000,
        'updated_at' => 1_700_000_001_000,
    ];
}

it('lists oauth providers with pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [googlePresetPayload()],
        'total_count' => 1,
    ]);
    $providers = new OauthProvidersManager($mock->transport());

    $list = $providers->list(new OauthProvidersListParams(limit: 10));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/oauth-providers');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=10');
});

it('creates an oauth provider with an idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, googlePresetPayload());
    $providers = new OauthProvidersManager($mock->transport());

    $created = $providers->create([
        'provider_kind' => 'preset',
        'provider_key' => 'google',
        'name' => 'Google',
        'client_id' => '123-abc.apps.googleusercontent.com',
        'client_secret' => 'GOCSPX-secret',
    ], idempotencyKey: 'idem-1');

    expect($created)->toBeInstanceOf(OauthProvider::class);
    expect($created->id)->toBe('oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($created->providerKind)->toBe('preset');
    expect($created->providerKey)->toBe('google');
    expect($created->scopes)->toBe(['openid', 'email', 'profile']);
    expect($created->attributeMapping)->toMatchArray(['email_address' => 'email']);
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $mockA = (new MockTransport)->enqueue(201, googlePresetPayload());
    $mockB = (new MockTransport)->enqueue(201, googlePresetPayload());

    (new OauthProvidersManager($mockA->transport()))
        ->create(['provider_kind' => 'preset', 'provider_key' => 'google']);
    (new OauthProvidersManager($mockB->transport()))
        ->create(['provider_key' => 'google', 'provider_kind' => 'preset']);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('gets, updates, deletes an oauth provider', function (): void {
    $payload = googlePresetPayload();
    $disabled = ['enabled' => false] + $payload;

    $mock = (new MockTransport)
        ->enqueue(body: $payload)
        ->enqueue(body: $disabled)
        ->enqueue(204);

    $providers = new OauthProvidersManager($mock->transport());

    $got = $providers->get('oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($got->enabled)->toBeTrue();

    $updated = $providers->update('oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA', ['enabled' => false]);
    expect($updated->enabled)->toBeFalse();

    $providers->delete('oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
    expect((string) $mock->requestAt(0)->getUri())
        ->toEndWith('/v1/oauth-providers/oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('test() returns probe result with errors and authorize_url', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?client_id=x',
        'userinfo_status' => 401,
        'errors' => [],
    ]);
    $providers = new OauthProvidersManager($mock->transport());

    $result = $providers->test('oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($result)->toBeInstanceOf(OauthProviderTestResult::class);
    expect($result->authorizeUrl)->toContain('client_id=x');
    expect($result->userinfoStatus)->toBe(401);
    expect($result->passed())->toBeTrue();
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/oauth-providers/oauthp_01HKX9SY9V7H7TF8C8K7J9X4ZA/test');
});

it('test() surfaces probe errors', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'authorize_url' => '',
        'userinfo_status' => null,
        'errors' => [
            [
                'code' => 'oauth_discovery_refresh_failed',
                'message' => 'Could not refresh discovery',
                'long_message' => 'The cached OIDC discovery doc could not be refreshed.',
            ],
        ],
    ]);
    $providers = new OauthProvidersManager($mock->transport());

    $result = $providers->test('oauthp_xyz');

    expect($result->passed())->toBeFalse();
    expect($result->userinfoStatus)->toBeNull();
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->code)->toBe('oauth_discovery_refresh_failed');
});

it('in-use deletion surfaces as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(409, [
        'errors' => [['code' => 'oauth_provider_in_use', 'message' => 'still linked', 'long_message' => '...']],
    ]);
    $providers = new OauthProvidersManager($mock->transport());

    expect(fn () => $providers->delete('oauthp_x'))->toThrow(ApiException::class);
});

it('Client::oauthProviders() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->oauthProviders())->toBeInstanceOf(OauthProvidersManager::class);
});
