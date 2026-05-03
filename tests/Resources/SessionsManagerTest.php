<?php

declare(strict_types=1);

use Authn\Sdk\Resources\SessionsListParams;
use Authn\Sdk\Resources\SessionsManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists sessions filtered by user_id', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [['id' => 'sess_1']], 'total_count' => 1]);
    $manager = new SessionsManager($mock->transport());

    $manager->list(new SessionsListParams(userId: 'user_1', status: 'active'));

    $uri = (string) $mock->lastRequest()->getUri();
    expect($uri)->toContain('/v1/sessions')
        ->toContain('user_id=user_1')
        ->toContain('status=active');
});

it('gets and revokes a session', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'sess_1', 'status' => 'active'])
        ->enqueue(body: ['id' => 'sess_1', 'status' => 'revoked']);
    $manager = new SessionsManager($mock->transport());

    expect($manager->get('sess_1')['id'])->toBe('sess_1');
    expect($manager->revoke('sess_1')['status'])->toBe('revoked');

    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/sessions/sess_1');
    expect((string) $mock->requestAt(1)->getUri())->toEndWith('/v1/sessions/sess_1/revoke');
    expect($mock->requestAt(1)->getMethod())->toBe('POST');
});

it('getToken returns the JWT and forwards the template path segment', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['jwt' => 'eyJ.default'])
        ->enqueue(body: ['jwt' => 'eyJ.tmpl']);
    $manager = new SessionsManager($mock->transport());

    expect($manager->getToken('sess_1'))->toBe('eyJ.default');
    expect($manager->getToken('sess_1', 'supabase'))->toBe('eyJ.tmpl');

    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/sessions/sess_1/tokens');
    expect((string) $mock->requestAt(1)->getUri())->toEndWith('/v1/sessions/sess_1/tokens/supabase');
});
