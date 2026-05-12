<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class ScimAttributeMapping
{
    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly array $mapping,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $organizationId = $payload['organization_id'] ?? null;
        $mapping = [];
        if (isset($payload['mapping']) && is_array($payload['mapping'])) {
            foreach ($payload['mapping'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $mapping[$key] = $value;
                }
            }
        }

        return new self(
            organizationId: is_string($organizationId) ? $organizationId : '',
            mapping: $mapping,
            raw: $payload,
        );
    }
}
