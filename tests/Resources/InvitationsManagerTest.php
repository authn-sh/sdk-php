<?php

declare(strict_types=1);

use Authn\Sdk\Resources\InvitationsListParams;
use Authn\Sdk\Resources\InvitationsManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('creates an invitation with an auto-generated idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'inv_1']);
    $manager = new InvitationsManager($mock->transport());

    $manager->create(['email_address' => 'a@b.com', 'redirect_url' => 'https://app/welcome']);

    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->not->toBe('');
});

it('bulk-creates invitations under /v1/invitations/bulk', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['data' => [['id' => 'inv_1'], ['id' => 'inv_2']]]);
    $manager = new InvitationsManager($mock->transport());

    $manager->bulkCreate([
        ['email_address' => 'a@b.com'],
        ['email_address' => 'c@d.com'],
    ]);

    $request = $mock->lastRequest();
    expect((string) $request->getUri())->toEndWith('/v1/invitations/bulk');
    expect((string) $request->getBody())
        ->toBe('{"invitations":[{"email_address":"a@b.com"},{"email_address":"c@d.com"}]}');
});

it('lists with status filter and revokes', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['data' => [['id' => 'inv_1']], 'total_count' => 1])
        ->enqueue(body: ['id' => 'inv_1', 'status' => 'revoked']);

    $manager = new InvitationsManager($mock->transport());
    $manager->list(new InvitationsListParams(status: 'pending'));
    $manager->revoke('inv_1');

    expect((string) $mock->requestAt(0)->getUri())->toContain('status=pending');
    expect((string) $mock->requestAt(1)->getUri())->toEndWith('/v1/invitations/inv_1/revoke');
});
