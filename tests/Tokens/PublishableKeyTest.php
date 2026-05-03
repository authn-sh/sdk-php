<?php

declare(strict_types=1);

use Authn\Sdk\Util\PublishableKey;

it('round-trips host through the publishable-key encoding', function (): void {
    $host = 'magnetic-zebra-42.authn.sh';
    $b64 = rtrim(strtr(base64_encode($host . '$'), '+/', '-_'), '=');
    $key = "pk_test_{$b64}";

    expect(PublishableKey::frontendApiUrl($key))->toBe('https://' . $host);
    expect(PublishableKey::isTestKey($key))->toBeTrue();
});

it('decodes a live key', function (): void {
    $b64 = rtrim(strtr(base64_encode('acme.authn.sh$'), '+/', '-_'), '=');
    $key = "pk_live_{$b64}";

    expect(PublishableKey::frontendApiUrl($key))->toBe('https://acme.authn.sh');
    expect(PublishableKey::isTestKey($key))->toBeFalse();
});

it('rejects malformed keys', function (): void {
    expect(fn () => PublishableKey::frontendApiUrl('garbage'))->toThrow(InvalidArgumentException::class);
    expect(fn () => PublishableKey::frontendApiUrl('pk_test_'))->toThrow(InvalidArgumentException::class);
    expect(fn () => PublishableKey::frontendApiUrl('pk_test_!!!!'))->toThrow(InvalidArgumentException::class);
});
