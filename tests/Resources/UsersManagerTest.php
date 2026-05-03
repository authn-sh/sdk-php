<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Http\ResourceNotFoundException;
use Authn\Sdk\Resources\PaginatedList;
use Authn\Sdk\Resources\UsersListParams;
use Authn\Sdk\Resources\UsersManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('lists users with filters and pagination', function (): void {
    $mock = (new MockTransport)->enqueue(body: [
        'data' => [['id' => 'user_1'], ['id' => 'user_2']],
        'total_count' => 2,
    ]);
    $users = new UsersManager($mock->transport());

    $list = $users->list(new UsersListParams(
        limit: 5,
        emailAddress: ['a@b.com', 'c@d.com'],
        query: 'jane',
    ));

    expect($list)->toBeInstanceOf(PaginatedList::class);
    expect($list->totalCount)->toBe(2);
    expect(iterator_to_array($list))->toHaveCount(2);

    $uri = (string) $mock->lastRequest()->getUri();
    expect($uri)->toContain('/v1/users');
    expect($uri)->toContain('limit=5');
    expect($uri)->toContain('email_address=a%40b.com');
    expect($uri)->toContain('email_address=c%40d.com');
    expect($uri)->toContain('query=jane');
});

it('counts users via /v1/users/count', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['total_count' => 42]);
    $users = (new Client(secretKey: 'sk', http: $mock))->users();
    // also confirm Client wires through

    expect($users->count(new UsersListParams(query: 'jane')))->toBe(42);
    expect((string) $mock->lastRequest()->getUri())
        ->toContain('/v1/users/count')
        ->toContain('query=jane');
});

it('creates a user, sends idempotency key, returns the resource', function (): void {
    $mock = (new MockTransport)->enqueue(201, ['id' => 'user_3', 'object' => 'user']);
    $users = new UsersManager($mock->transport());

    $created = $users->create(['email_address' => ['x@y.com']], idempotencyKey: 'idem-1');

    expect($created['id'])->toBe('user_3');

    $request = $mock->lastRequest();
    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://api.authn.sh/v1/users');
    expect($request->getHeaderLine('Idempotency-Key'))->toBe('idem-1');
    expect((string) $request->getBody())->toBe('{"email_address":["x@y.com"]}');
});

it('auto-generates a stable idempotency key when caller omits one', function (): void {
    $mockA = (new MockTransport)->enqueue(201, ['id' => 'user_a']);
    $mockB = (new MockTransport)->enqueue(201, ['id' => 'user_a']);

    (new UsersManager($mockA->transport()))
        ->create(['email_address' => ['x@y.com'], 'first_name' => 'Jane']);
    (new UsersManager($mockB->transport()))
        ->create(['first_name' => 'Jane', 'email_address' => ['x@y.com']]);

    expect($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->not->toBe('')
        ->and($mockA->lastRequest()->getHeaderLine('Idempotency-Key'))
        ->toBe($mockB->lastRequest()->getHeaderLine('Idempotency-Key'));
});

it('gets, updates, deletes a user', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'user_1', 'first_name' => 'Jane'])
        ->enqueue(body: ['id' => 'user_1', 'first_name' => 'Janet'])
        ->enqueue(204);

    $manager = new UsersManager($mock->transport());

    expect($manager->get('user_1')['first_name'])->toBe('Jane');
    expect($manager->update('user_1', ['first_name' => 'Janet'])['first_name'])->toBe('Janet');
    $manager->delete('user_1');

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect($mock->requestAt(2)->getMethod())->toBe('DELETE');
});

it('moderation actions hit the right paths', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['id' => 'user_1', 'banned' => true])
        ->enqueue(body: ['id' => 'user_1', 'banned' => false])
        ->enqueue(body: ['id' => 'user_1', 'locked' => true])
        ->enqueue(body: ['id' => 'user_1', 'locked' => false]);

    $m = new UsersManager($mock->transport());
    $m->ban('user_1');
    $m->unban('user_1');
    $m->lock('user_1');
    $m->unlock('user_1');

    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/users/user_1/ban');
    expect((string) $mock->requestAt(1)->getUri())->toEndWith('/v1/users/user_1/unban');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/users/user_1/lock');
    expect((string) $mock->requestAt(3)->getUri())->toEndWith('/v1/users/user_1/unlock');
});

it('uploads a profile image as multipart/form-data', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'user_1', 'has_image' => true]);
    $m = new UsersManager($mock->transport());

    $m->uploadProfileImage('user_1', "\x89PNGfakebytes", 'image/png');

    $request = $mock->lastRequest();
    $contentType = $request->getHeaderLine('Content-Type');
    expect($contentType)->toStartWith('multipart/form-data; boundary=');

    $body = (string) $request->getBody();
    expect($body)->toContain('Content-Disposition: form-data; name="file"; filename="profile_image"');
    expect($body)->toContain('Content-Type: image/png');
    expect($body)->toContain('PNGfakebytes');
});

it('verifyPassword unwraps the verified flag', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['verified' => true])
        ->enqueue(body: ['verified' => false]);

    $m = new UsersManager($mock->transport());

    expect($m->verifyPassword('user_1', 'correct'))->toBeTrue();
    expect($m->verifyPassword('user_1', 'wrong'))->toBeFalse();

    expect((string) $mock->requestAt(0)->getBody())->toBe('{"password":"correct"}');
    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/users/user_1/verify_password');
});

it('listSessions returns a PaginatedList', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['data' => [['id' => 'sess_1']], 'total_count' => 1]);
    $m = new UsersManager($mock->transport());

    $list = $m->listSessions('user_1');

    expect($list->totalCount)->toBe(1);
    expect((string) $mock->lastRequest()->getUri())->toEndWith('/v1/users/user_1/sessions');
});

it('returns an empty list for organization endpoints (orgs land in v0.2)', function (): void {
    $mock = new MockTransport;
    $m = new UsersManager($mock->transport());

    expect($m->listOrganizationMemberships('user_1'))->toBeInstanceOf(PaginatedList::class)
        ->and($m->listOrganizationMemberships('user_1')->totalCount)->toBe(0);
    expect($m->listOrganizationInvitations('user_1')->totalCount)->toBe(0);
    expect($mock->requests)->toHaveCount(0);
});

it('getOauthAccessToken raises ResourceNotFoundException on a v0.1 BAPI', function (): void {
    $mock = (new MockTransport)->enqueue(404, ['errors' => [['code' => 'not_found', 'message' => 'not found']]]);
    $m = new UsersManager($mock->transport());

    expect(fn () => $m->getOauthAccessToken('user_1', 'google'))->toThrow(ResourceNotFoundException::class);
    expect((string) $mock->lastRequest()->getUri())
        ->toEndWith('/v1/users/user_1/oauth_access_tokens/google');
});

it('updateMetadata sends a PATCH to /metadata', function (): void {
    $mock = (new MockTransport)->enqueue(body: ['id' => 'user_1']);
    $m = new UsersManager($mock->transport());

    $m->updateMetadata('user_1', ['public_metadata' => ['plan' => 'pro']]);

    $request = $mock->lastRequest();
    expect($request->getMethod())->toBe('PATCH');
    expect((string) $request->getUri())->toEndWith('/v1/users/user_1/metadata');
    expect((string) $request->getBody())->toBe('{"public_metadata":{"plan":"pro"}}');
});
