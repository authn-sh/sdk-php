<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\Appearance;
use Authn\Sdk\Resources\AppearanceManager;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function fullAppearancePayload(): array
{
    return [
        'variables' => [
            'colorPrimary' => '#0a84ff',
            'colorTextOnPrimary' => '#ffffff',
            'borderRadius' => '0.5rem',
        ],
        'elements' => [
            'card' => 'shadow-2xl border border-slate-700',
            'formButtonPrimary' => 'tracking-wide uppercase',
        ],
        'layout' => [
            'logoImageUrl' => 'https://cdn.acme.com/logo-dark.svg',
            'socialButtonsPlacement' => 'top',
            'animations' => true,
            'showOptionalFields' => false,
        ],
    ];
}

it('gets the appearance config (defaults — empty payload)', function (): void {
    $mock = (new MockTransport)->enqueue(body: []);
    $manager = new AppearanceManager($mock->transport());

    $appearance = $manager->get();

    expect($appearance)->toBeInstanceOf(Appearance::class);
    expect($appearance->variables)->toBe([]);
    expect($appearance->elements)->toBe([]);
    expect($appearance->layout)->toBe([]);
    expect($mock->lastRequest()->getMethod())->toBe('GET');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/instance/appearance');
});

it('parses a fully-populated appearance payload', function (): void {
    $mock = (new MockTransport)->enqueue(body: fullAppearancePayload());
    $manager = new AppearanceManager($mock->transport());

    $appearance = $manager->get();

    expect($appearance->variables)->toMatchArray(['colorPrimary' => '#0a84ff']);
    expect($appearance->elements)->toMatchArray(['card' => 'shadow-2xl border border-slate-700']);
    expect($appearance->layout)->toMatchArray([
        'logoImageUrl' => 'https://cdn.acme.com/logo-dark.svg',
        'animations' => true,
        'showOptionalFields' => false,
    ]);
});

it('PUT replaces the appearance with a serialized payload', function (): void {
    $mock = (new MockTransport)->enqueue(body: fullAppearancePayload());
    $manager = new AppearanceManager($mock->transport());

    $next = new Appearance(
        variables: ['colorPrimary' => '#0a84ff', 'colorTextOnPrimary' => '#ffffff', 'borderRadius' => '0.5rem'],
        elements: ['card' => 'shadow-2xl border border-slate-700', 'formButtonPrimary' => 'tracking-wide uppercase'],
        layout: ['logoImageUrl' => 'https://cdn.acme.com/logo-dark.svg', 'socialButtonsPlacement' => 'top', 'animations' => true, 'showOptionalFields' => false],
    );

    $result = $manager->put($next, idempotencyKey: 'idem-put');

    expect($result)->toBeInstanceOf(Appearance::class);
    expect($mock->lastRequest()->getMethod())->toBe('PUT');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-put');
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/instance/appearance');
    /** @var array<string, array<string, mixed>> $body */
    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toHaveKeys(['variables', 'elements', 'layout']);
    expect($body['variables'])->toMatchArray(['colorPrimary' => '#0a84ff']);
});

it('PATCH sends only the supplied keys (sparse deep-merge)', function (): void {
    $mock = (new MockTransport)->enqueue(body: fullAppearancePayload());
    $manager = new AppearanceManager($mock->transport());

    $manager->patch(['variables' => ['colorPrimary' => '#16a34a', 'colorTextOnPrimary' => '#ffffff']]);

    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toBe([
        'variables' => ['colorPrimary' => '#16a34a', 'colorTextOnPrimary' => '#ffffff'],
    ]);
});

it('PUT an empty Appearance sends an empty body', function (): void {
    $mock = (new MockTransport)->enqueue(body: []);
    $manager = new AppearanceManager($mock->transport());

    $manager->put(new Appearance);

    $body = (string) $mock->lastRequest()->getBody();
    expect($body)->toBeIn(['{}', '[]']);
});

it('surfaces 422 from PUT as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(422, [
        'errors' => [['code' => 'invalid_variable', 'message' => 'bad token', 'long_message' => '...']],
    ]);
    $manager = new AppearanceManager($mock->transport());

    expect(fn () => $manager->put(new Appearance(variables: ['colorPrimary' => 'nope'])))
        ->toThrow(ApiException::class);
});

it('Client::appearance() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: []);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->appearance())->toBeInstanceOf(AppearanceManager::class);
});
