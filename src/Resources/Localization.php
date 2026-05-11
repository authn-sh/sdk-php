<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class Localization
{
    /**
     * @param  list<string>  $supportedLocales
     * @param  array<string, array<string, string>>  $overrides
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $defaultLocale,
        public readonly string $fallbackLocale,
        public readonly array $supportedLocales,
        public readonly array $overrides,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            defaultLocale: self::stringField($payload, 'default_locale'),
            fallbackLocale: self::stringField($payload, 'fallback_locale'),
            supportedLocales: self::stringList($payload['supported_locales'] ?? []),
            overrides: self::overridesMap($payload['overrides'] ?? []),
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'default_locale' => $this->defaultLocale,
            'fallback_locale' => $this->fallbackLocale,
            'supported_locales' => $this->supportedLocales,
            'overrides' => $this->overrides === [] ? new \stdClass : $this->overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function overridesMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $locale => $catalog) {
            if (! is_string($locale) || ! is_array($catalog)) {
                continue;
            }
            $entry = [];
            foreach ($catalog as $key => $translation) {
                if (is_string($key) && is_string($translation)) {
                    $entry[$key] = $translation;
                }
            }
            $out[$locale] = $entry;
        }

        return $out;
    }
}
