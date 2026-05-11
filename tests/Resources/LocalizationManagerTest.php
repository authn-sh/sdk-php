<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\Localization;
use Authn\Sdk\Resources\LocalizationManager;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function defaultsOnlyLocalizationPayload(): array
{
    return [
        'default_locale' => 'en-US',
        'fallback_locale' => 'en-US',
        'supported_locales' => ['en-US', 'pt-BR', 'es-ES', 'fr-FR', 'de-DE'],
        'overrides' => [],
    ];
}

/**
 * @return array<string, mixed>
 */
function ptBROverrideLocalizationPayload(): array
{
    return [
        'default_locale' => 'pt-BR',
        'fallback_locale' => 'en-US',
        'supported_locales' => ['en-US', 'pt-BR'],
        'overrides' => [
            'pt-BR' => [
                'signIn.start.title' => 'Bem-vindo de volta',
                'signIn.start.actionText' => 'Não tem uma conta?',
            ],
        ],
    ];
}

it('gets the localization config with defaults only', function (): void {
    $mock = (new MockTransport)->enqueue(body: defaultsOnlyLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $loc = $manager->get();

    expect($loc)->toBeInstanceOf(Localization::class);
    expect($loc->defaultLocale)->toBe('en-US');
    expect($loc->fallbackLocale)->toBe('en-US');
    expect($loc->supportedLocales)->toBe(['en-US', 'pt-BR', 'es-ES', 'fr-FR', 'de-DE']);
    expect($loc->overrides)->toBe([]);
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/instance/localization');
});

it('parses per-locale overrides', function (): void {
    $mock = (new MockTransport)->enqueue(body: ptBROverrideLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $loc = $manager->get();

    expect($loc->overrides)->toHaveKey('pt-BR');
    expect($loc->overrides['pt-BR'])->toMatchArray([
        'signIn.start.title' => 'Bem-vindo de volta',
    ]);
});

it('PUT replaces the localization with a serialized payload', function (): void {
    $mock = (new MockTransport)->enqueue(body: ptBROverrideLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $next = new Localization(
        defaultLocale: 'pt-BR',
        fallbackLocale: 'en-US',
        supportedLocales: ['en-US', 'pt-BR'],
        overrides: ['pt-BR' => ['signIn.start.title' => 'Bem-vindo de volta']],
    );

    $manager->put($next, idempotencyKey: 'idem-put');

    expect($mock->lastRequest()->getMethod())->toBe('PUT');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-put');
    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toBe([
        'default_locale' => 'pt-BR',
        'fallback_locale' => 'en-US',
        'supported_locales' => ['en-US', 'pt-BR'],
        'overrides' => ['pt-BR' => ['signIn.start.title' => 'Bem-vindo de volta']],
    ]);
});

it('PATCH adds a single override (sparse merge)', function (): void {
    $mock = (new MockTransport)->enqueue(body: ptBROverrideLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $manager->patch([
        'overrides' => [
            'pt-BR' => ['signIn.start.title' => 'Bem-vindo de volta'],
        ],
    ]);

    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toBe([
        'overrides' => [
            'pt-BR' => ['signIn.start.title' => 'Bem-vindo de volta'],
        ],
    ]);
});

it('PATCH with explicit null on a key clears that override', function (): void {
    $mock = (new MockTransport)->enqueue(body: defaultsOnlyLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $manager->patch([
        'overrides' => [
            'pt-BR' => ['signIn.start.title' => null],
        ],
    ]);

    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toBe([
        'overrides' => [
            'pt-BR' => ['signIn.start.title' => null],
        ],
    ]);
});

it('PATCH can change the default_locale alone', function (): void {
    $mock = (new MockTransport)->enqueue(body: ptBROverrideLocalizationPayload());
    $manager = new LocalizationManager($mock->transport());

    $manager->patch(['default_locale' => 'pt-BR']);

    $body = json_decode((string) $mock->lastRequest()->getBody(), true);
    expect($body)->toBe(['default_locale' => 'pt-BR']);
});

it('surfaces 422 unknown_localization_key as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(422, [
        'errors' => [['code' => 'unknown_localization_key', 'message' => 'unknown', 'long_message' => '...']],
    ]);
    $manager = new LocalizationManager($mock->transport());

    expect(fn () => $manager->patch(['overrides' => ['en-US' => ['nonsense.key' => 'x']]]))
        ->toThrow(ApiException::class);
});

it('Client::localization() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: defaultsOnlyLocalizationPayload());
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->localization())->toBeInstanceOf(LocalizationManager::class);
});
