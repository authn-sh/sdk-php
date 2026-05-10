<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

final class SmsTemplatesManager extends Manager
{
    /**
     * @return list<SmsTemplate>
     */
    public function list(): array
    {
        $payload = $this->transport->sendAny('GET', '/v1/sms-templates');

        $rows = [];
        foreach ($payload as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $rows[] = SmsTemplate::fromPayload($item);
            }
        }

        return $rows;
    }

    public function get(string $slug): SmsTemplate
    {
        $payload = $this->transport->send('GET', '/v1/sms-templates/' . rawurlencode($slug));

        return SmsTemplate::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $slug, array $data, ?string $idempotencyKey = null): SmsTemplate
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/sms-templates/' . rawurlencode($slug),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return SmsTemplate::fromPayload($payload);
    }

    public function revert(string $slug, ?string $idempotencyKey = null): SmsTemplate
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/sms-templates/' . rawurlencode($slug) . '/revert',
            ['idempotencyKey' => $idempotencyKey],
        );

        return SmsTemplate::fromPayload($payload);
    }
}
