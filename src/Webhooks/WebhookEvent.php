<?php

declare(strict_types=1);

namespace Authn\Sdk\Webhooks;

final class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly int $timestamp,
        public readonly string $instanceId,
        public readonly bool $wasTest,
        public readonly string $messageId,
    ) {}
}
