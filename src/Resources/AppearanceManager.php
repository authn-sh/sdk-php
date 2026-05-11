<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class AppearanceManager extends Manager
{
    public function get(): Appearance
    {
        $payload = $this->transport->send('GET', '/v1/instance/appearance');

        return Appearance::fromPayload($payload);
    }

    public function put(Appearance $appearance, ?string $idempotencyKey = null): Appearance
    {
        $payload = $this->transport->send('PUT', '/v1/instance/appearance', [
            'body' => $appearance->toPayload(),
            'idempotencyKey' => $idempotencyKey,
        ]);

        return Appearance::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $partial
     */
    public function patch(array $partial, ?string $idempotencyKey = null): Appearance
    {
        $payload = $this->transport->send('PATCH', '/v1/instance/appearance', [
            'body' => $partial,
            'idempotencyKey' => $idempotencyKey,
        ]);

        return Appearance::fromPayload($payload);
    }
}
