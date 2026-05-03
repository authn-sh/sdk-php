<?php

declare(strict_types=1);

namespace Authn\Sdk\Util;

use InvalidArgumentException;

/**
 * Helpers for the `pk_test_…` / `pk_live_…` publishable key format.
 *
 * The suffix after `pk_(test|live)_` is the base64url-encoded Frontend API host
 * with a trailing `$` character (used as a basic validity marker).
 */
final class PublishableKey
{
    public static function frontendApiUrl(string $publishableKey): string
    {
        if (! preg_match('/^pk_(test|live)_([A-Za-z0-9_-]+)$/', $publishableKey, $m)) {
            throw new InvalidArgumentException('Malformed publishable key: expected pk_test_… or pk_live_…');
        }

        $encoded = strtr($m[2], '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Malformed publishable key: suffix is not valid base64url.');
        }

        $host = rtrim($decoded, '$');
        if ($host === '') {
            throw new InvalidArgumentException('Malformed publishable key: empty Frontend API host.');
        }

        return 'https://' . $host;
    }

    public static function isTestKey(string $publishableKey): bool
    {
        return str_starts_with($publishableKey, 'pk_test_');
    }
}
