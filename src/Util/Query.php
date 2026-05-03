<?php

declare(strict_types=1);

namespace Authn\Sdk\Util;

final class Query
{
    /**
     * Build a query string with repeated keys for array values
     * (`email_address=a&email_address=b` rather than the bracketed PHP default).
     *
     * @param  array<string, scalar|null|array<int, scalar|null>>  $params
     */
    public static function build(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $pairs[] = rawurlencode($key) . '=' . self::encodeScalar($v);
                }

                continue;
            }

            $pairs[] = rawurlencode($key) . '=' . self::encodeScalar($value);
        }

        return implode('&', $pairs);
    }

    private static function encodeScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return rawurlencode((string) $value);
        }

        return rawurlencode((string) $value); // @phpstan-ignore-line cast.string
    }
}
