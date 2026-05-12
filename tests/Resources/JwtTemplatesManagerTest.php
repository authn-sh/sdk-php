<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ApiException;
use Authn\Sdk\Resources\JwtTemplate;
use Authn\Sdk\Resources\JwtTemplatesManager;
use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

/**
 * @return array<string, mixed>
 */
function jwtTemplatePayload(): array
{
    return [
        'id' => 'jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA',
        'object' => 'jwt_template',
        'name' => 'supabase',
        'claims' => [
            'sub' => '{{user.id}}',
            'email' => '{{user.primary_email_address}}',
            'role' => 'authenticated',
        ],
        'lifetime' => 60,
        'allowed_clock_skew' => 5,
        'signing_algorithm' => 'RS256',
        'created_at' => 1_714_723_000_000,
        'updated_at' => 1_714_723_000_000,
    ];
}

it('lists JWT templates', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [jwtTemplatePayload()],
        'total_count' => 1,
    ]);
    $manager = new JwtTemplatesManager($mock->transport());

    $list = $manager->list(new ListParams(limit: 25));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/jwt-templates');
    expect((string) $mock->lastRequest()->getUri())->toContain('limit=25');
});

it('creates a JWT template with an idempotency key and parses the response', function (): void {
    $mock = (new MockTransport)->enqueue(201, jwtTemplatePayload());
    $manager = new JwtTemplatesManager($mock->transport());

    $template = $manager->create([
        'name' => 'supabase',
        'claims' => [
            'sub' => '{{user.id}}',
            'email' => '{{user.primary_email_address}}',
            'role' => 'authenticated',
        ],
    ], idempotencyKey: 'idem-1');

    expect($template)->toBeInstanceOf(JwtTemplate::class);
    expect($template->id)->toBe('jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA');
    expect($template->name)->toBe('supabase');
    expect($template->claims)->toHaveKey('sub');
    expect($template->claims['sub'])->toBe('{{user.id}}');
    expect($template->lifetime)->toBe(60);
    expect($template->allowedClockSkew)->toBe(5);
    expect($template->signingAlgorithm)->toBe('RS256');

    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
});

it('auto-generates a stable idempotency key when caller omits one on create', function (): void {
    $mockA = (new MockTransport)->enqueue(201, jwtTemplatePayload());
    $mockB = (new MockTransport)->enqueue(201, jwtTemplatePayload());

    (new JwtTemplatesManager($mockA->transport()))->create([
        'name' => 'acme_rpc',
        'claims' => ['sub' => '{{user.id}}'],
    ]);
    (new JwtTemplatesManager($mockB->transport()))->create([
        'claims' => ['sub' => '{{user.id}}'],
        'name' => 'acme_rpc',
    ]);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('parses an HS256 template with a custom claim set', function (): void {
    $payload = jwtTemplatePayload();
    $payload['id'] = 'jtmpl_HS';
    $payload['name'] = 'legacy_hs256';
    $payload['signing_algorithm'] = 'HS256';
    $payload['claims'] = ['sub' => '{{user.id}}', 'iss' => 'https://acme.example', 'aud' => 'legacy-rpc'];

    $mock = (new MockTransport)->enqueue(body: $payload);
    $manager = new JwtTemplatesManager($mock->transport());

    $template = $manager->get('jtmpl_HS');

    expect($template->signingAlgorithm)->toBe('HS256');
    expect($template->claims['iss'])->toBe('https://acme.example');
    expect($template->claims['aud'])->toBe('legacy-rpc');
});

it('updates a JWT template via PATCH', function (): void {
    $updated = jwtTemplatePayload();
    $updated['lifetime'] = 600;

    $mock = (new MockTransport)->enqueue(body: $updated);
    $manager = new JwtTemplatesManager($mock->transport());

    $template = $manager->update('jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA', ['lifetime' => 600]);

    expect($template->lifetime)->toBe(600);
    expect($mock->lastRequest()->getMethod())->toBe('PATCH');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/jwt-templates/jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA');
});

it('deletes a JWT template', function (): void {
    $mock = (new MockTransport)->enqueue(204);
    $manager = new JwtTemplatesManager($mock->transport());

    $manager->delete('jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($mock->lastRequest()->getMethod())->toBe('DELETE');
});

it('surfaces 409 delete-in-use as ApiException', function (): void {
    $mock = (new MockTransport)->enqueue(409, [
        'errors' => [['code' => 'jwt_template_in_use', 'message' => 'still referenced', 'long_message' => '...']],
    ]);
    $manager = new JwtTemplatesManager($mock->transport());

    expect(fn () => $manager->delete('jtmpl_in_use'))->toThrow(ApiException::class);
});

it('coerces missing or non-array claims to an empty map', function (): void {
    $payload = jwtTemplatePayload();
    unset($payload['claims']);

    $mock = (new MockTransport)->enqueue(body: $payload);
    $manager = new JwtTemplatesManager($mock->transport());

    $template = $manager->get('jtmpl_01HKX9SY9V7H7TF8C8K7J9X4ZA');

    expect($template->claims)->toBe([]);
});

it('Client::jwtTemplates() wires through correctly', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $client = new Client(secretKey: 'sk', http: $mock);

    expect($client->jwtTemplates())->toBeInstanceOf(JwtTemplatesManager::class);
});
