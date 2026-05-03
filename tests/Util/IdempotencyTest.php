<?php

declare(strict_types=1);

use Authn\Sdk\Util\Idempotency;

it('produces the same key regardless of key order', function (): void {
    $a = Idempotency::keyFor(['a' => 1, 'b' => ['x' => 1, 'y' => 2]]);
    $b = Idempotency::keyFor(['b' => ['y' => 2, 'x' => 1], 'a' => 1]);

    expect($a)->toBe($b);
});

it('preserves order for list payloads', function (): void {
    $a = Idempotency::keyFor(['invitations' => [['email' => 'a'], ['email' => 'b']]]);
    $b = Idempotency::keyFor(['invitations' => [['email' => 'b'], ['email' => 'a']]]);

    expect($a)->not->toBe($b);
});

it('returns a stable, prefixed sha256 fingerprint', function (): void {
    $key = Idempotency::keyFor(['email_address' => ['a@b.com']]);

    expect($key)->toStartWith('authn-')
        ->and(strlen($key))->toBe(strlen('authn-') + 64);
});
