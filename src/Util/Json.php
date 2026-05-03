<?php

declare(strict_types=1);

namespace Authn\Sdk\Util;

use JsonException;

final class Json
{
    /**
     * @param  array<int|string, mixed>  $value
     */
    public static function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON object body into a string-keyed array.
     *
     * Returns an empty array on empty input, invalid JSON, or non-object payloads
     * (e.g. a JSON array, scalar, or null). All authn.sh API responses are objects.
     *
     * @return array<string, mixed>
     */
    public static function decode(string $value): array
    {
        if ($value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
