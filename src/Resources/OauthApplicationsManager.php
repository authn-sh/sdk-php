<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class OauthApplicationsManager extends Manager
{
    public function list(?ListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/oauth-applications', [
            'query' => ($params ?? new ListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    /**
     * Returns {@see OauthApplicationWithSecret} for confidential apps (the
     * `client_secret` plaintext is included on the create response exactly
     * once) and {@see OauthApplication} for public (PKCE-only) apps.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): OauthApplication
    {
        $payload = $this->transport->send('POST', '/v1/oauth-applications', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);

        return self::hydrate($payload);
    }

    public function get(string $oauthApplicationId): OauthApplication
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/oauth-applications/' . rawurlencode($oauthApplicationId),
        );

        return OauthApplication::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $oauthApplicationId, array $data, ?string $idempotencyKey = null): OauthApplication
    {
        $payload = $this->transport->send(
            'PATCH',
            '/v1/oauth-applications/' . rawurlencode($oauthApplicationId),
            [
                'body' => $data,
                'idempotencyKey' => $idempotencyKey,
            ],
        );

        return OauthApplication::fromPayload($payload);
    }

    public function delete(string $oauthApplicationId): void
    {
        $this->transport->send(
            'DELETE',
            '/v1/oauth-applications/' . rawurlencode($oauthApplicationId),
        );
    }

    /**
     * Mint a fresh `client_secret`. Refused with `409 oauth_application_public_client`
     * when the application has `is_public: true`. The response is the only time
     * the new plaintext is returned.
     */
    public function rotateSecret(string $oauthApplicationId, ?string $idempotencyKey = null): OauthApplicationWithSecret
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/oauth-applications/' . rawurlencode($oauthApplicationId) . '/rotate-secret',
            ['idempotencyKey' => $idempotencyKey],
        );

        return OauthApplicationWithSecret::fromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hydrate(array $payload): OauthApplication
    {
        $secret = $payload['client_secret'] ?? null;

        return is_string($secret) && $secret !== ''
            ? OauthApplicationWithSecret::fromPayload($payload)
            : OauthApplication::fromPayload($payload);
    }
}
