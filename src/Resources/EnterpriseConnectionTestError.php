<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class EnterpriseConnectionTestError
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $longMessage,
        public readonly array $meta,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $meta = [];
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            foreach ($payload['meta'] as $key => $value) {
                if (is_string($key)) {
                    $meta[$key] = $value;
                }
            }
        }

        return new self(
            code: isset($payload['code']) && is_string($payload['code']) ? $payload['code'] : '',
            message: isset($payload['message']) && is_string($payload['message']) ? $payload['message'] : '',
            longMessage: isset($payload['long_message']) && is_string($payload['long_message']) ? $payload['long_message'] : '',
            meta: $meta,
        );
    }
}
