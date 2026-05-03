<?php

declare(strict_types=1);

use Authn\Sdk\Client;
use Authn\Sdk\Resources\UsersManager;

it('instantiates with just a secret key (auto-discovered PSR-18 client)', function (): void {
    $client = new Client(secretKey: 'sk_test_abc');

    expect($client->config()->apiUrl)->toBe('https://api.authn.sh');
    expect($client->users())->toBeInstanceOf(UsersManager::class);
});

it('rejects an empty secret key', function (): void {
    expect(fn () => new Client(secretKey: ''))->toThrow(InvalidArgumentException::class);
});
