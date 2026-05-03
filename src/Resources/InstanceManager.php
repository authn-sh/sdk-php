<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class InstanceManager extends Manager
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->transport->send('GET', '/v1/instance');
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function update(array $patch): array
    {
        return $this->transport->send('PATCH', '/v1/instance', [
            'body' => $patch,
        ]);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function updateRestrictions(array $patch): array
    {
        return $this->transport->send('PATCH', '/v1/instance/restrictions', [
            'body' => $patch,
        ]);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function updateOrganizationSettings(array $patch): array
    {
        return $this->transport->send('PATCH', '/v1/instance/organization_settings', [
            'body' => $patch,
        ]);
    }
}
