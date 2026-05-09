<?php

declare(strict_types=1);

use Authn\Sdk\Resources\ListParams;
use Authn\Sdk\Resources\OrganizationInvitationsManager;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists invitations under the correct org path', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'inv_1', 'status' => 'pending']],
        'total_count' => 1,
    ]);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $list = $manager->list();

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toContain('/v1/organizations/org_1/invitations');
});

it('creates an invitation with an auto-generated idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'inv_1']);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $manager->create(['email_address' => 'a@b.com', 'role' => 'basic_member']);

    $request = $mock->lastRequest();
    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toEndWith('/v1/organizations/org_1/invitations');
    expect($request->getHeaderLine('Idempotency-Key'))->not->toBe('');
});

it('bulk-creates invitations under /bulk path', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['data' => [['id' => 'inv_1'], ['id' => 'inv_2']]]);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $manager->bulkCreate([
        ['email_address' => 'a@b.com'],
        ['email_address' => 'c@d.com'],
    ]);

    $request = $mock->lastRequest();
    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toEndWith('/v1/organizations/org_1/invitations/bulk');
    expect((string) $request->getBody())
        ->toBe('{"invitations":[{"email_address":"a@b.com"},{"email_address":"c@d.com"}]}');
    expect($request->getHeaderLine('Idempotency-Key'))->not->toBe('');
});

it('bulk-creates with a caller-supplied idempotency key', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['data' => []]);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $manager->bulkCreate([['email_address' => 'a@b.com']], idempotencyKey: 'bulk-key');

    expect($mock->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('bulk-key');
});

it('revokes an invitation via POST to /revoke', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'inv_1', 'status' => 'revoked']);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $result = $manager->revoke('inv_1');

    expect($result['status'])->toBe('revoked');
    expect($mock->lastRequest()->getMethod())->toBe('POST');
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/organizations/org_1/invitations/inv_1/revoke');
});

it('lists with pagination params', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [], 'total_count' => 0]);
    $manager = new OrganizationInvitationsManager($mock->transport(), 'org_1');

    $manager->list(new ListParams(limit: 10, offset: 20));

    $uri = (string) $mock->lastRequest()->getUri();
    expect($uri)->toContain('limit=10');
    expect($uri)->toContain('offset=20');
});
