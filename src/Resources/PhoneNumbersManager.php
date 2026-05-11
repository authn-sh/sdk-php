<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class PhoneNumbersManager extends Manager
{
    public function list(?PhoneNumbersListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/phone-numbers', [
            'query' => ($params ?? new PhoneNumbersListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): PhoneNumber
    {
        $payload = $this->transport->send('POST', '/v1/phone-numbers', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);

        return PhoneNumber::fromPayload($payload);
    }

    public function get(string $phoneNumberId): PhoneNumber
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/phone-numbers/' . rawurlencode($phoneNumberId),
        );

        return PhoneNumber::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $phoneNumberId, array $data, ?string $idempotencyKey = null): PhoneNumber
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/phone-numbers/' . rawurlencode($phoneNumberId),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return PhoneNumber::fromPayload($payload);
    }

    public function delete(string $phoneNumberId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/phone-numbers/' . rawurlencode($phoneNumberId),
        );
    }
}
