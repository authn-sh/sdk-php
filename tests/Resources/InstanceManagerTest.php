<?php

declare(strict_types=1);

use Authn\Sdk\Resources\InstanceManager;
use Authn\Sdk\Tests\Support\MockTransport;

it('reads and updates instance settings', function (): void {
    $mock = (new MockTransport)
        ->enqueue(body: ['object' => 'instance', 'home_origin' => 'https://app'])
        ->enqueue(body: ['object' => 'instance'])
        ->enqueue(body: ['object' => 'instance_restrictions'])
        ->enqueue(body: ['object' => 'organization_settings']);

    $m = new InstanceManager($mock->transport());
    $m->get();
    $m->update(['home_origin' => 'https://app2']);
    $m->updateRestrictions(['allowlist_enabled' => true]);
    $m->updateOrganizationSettings(['enabled' => false]);

    expect($mock->requestAt(0)->getMethod())->toBe('GET');
    expect((string) $mock->requestAt(0)->getUri())->toEndWith('/v1/instance');
    expect($mock->requestAt(1)->getMethod())->toBe('PATCH');
    expect((string) $mock->requestAt(2)->getUri())->toEndWith('/v1/instance/restrictions');
    expect((string) $mock->requestAt(3)->getUri())->toEndWith('/v1/instance/organization_settings');
});
