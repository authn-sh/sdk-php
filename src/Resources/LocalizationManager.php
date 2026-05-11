<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class LocalizationManager extends Manager
{
    public function get(): Localization
    {
        $payload = $this->transport->send('GET', '/v1/instance/localization');

        return Localization::fromPayload($payload);
    }

    public function put(Localization $localization, ?string $idempotencyKey = null): Localization
    {
        $payload = $this->transport->send('PUT', '/v1/instance/localization', [
            'body' => $localization->toPayload(),
            'idempotencyKey' => $idempotencyKey,
        ]);

        return Localization::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $partial
     */
    public function patch(array $partial, ?string $idempotencyKey = null): Localization
    {
        $payload = $this->transport->send('PATCH', '/v1/instance/localization', [
            'body' => $partial,
            'idempotencyKey' => $idempotencyKey,
        ]);

        return Localization::fromPayload($payload);
    }
}
