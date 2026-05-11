<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class Appearance
{
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $elements
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly array $variables = [],
        public readonly array $elements = [],
        public readonly array $layout = [],
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            variables: self::stringMap($payload['variables'] ?? []),
            elements: self::stringMap($payload['elements'] ?? []),
            layout: self::mixedMap($payload['layout'] ?? []),
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $out = [];
        if ($this->variables !== []) {
            $out['variables'] = $this->variables;
        }
        if ($this->elements !== []) {
            $out['elements'] = $this->elements;
        }
        if ($this->layout !== []) {
            $out['layout'] = $this->layout;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mixedMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
