<?php

declare(strict_types=1);

use Authn\Sdk\Resources\RedirectUrlsManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('manages redirect URLs', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['data' => [], 'total_count' => 0])
        ->enqueue(201, ['id' => 'rurl_1', 'url' => 'https://app/cb'])
        ->enqueue(body: ['id' => 'rurl_1'])
        ->enqueue(204);

    $m = new RedirectUrlsManager($mock->transport());
    $m->list();
    $m->create('https://app/cb');
    $m->get('rurl_1');
    $m->delete('rurl_1');

    expect($mock->requestAt(1)->getMethod())->toBe('POST');
    expect((string) $mock->requestAt(1)->getBody())->toBe('{"url":"https://app/cb"}');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/redirect_urls/rurl_1');
    expect($mock->requestAt(3)->getMethod())->toBe('DELETE');
});
