<?php

declare(strict_types=1);

namespace Authn\Sdk\Resources;

use Authn\Sdk\Util\Idempotency;

final class UsersManager extends Manager
{
    public function list(?UsersListParams $params = null): PaginatedList
    {
        $payload = $this->transport->send('GET', '/v1/users', [
            'query' => ($params ?? new UsersListParams)->toQuery(),
        ]);

        return PaginatedList::fromResponse($payload);
    }

    public function count(?UsersListParams $params = null): int
    {
        $payload = $this->transport->send('GET', '/v1/users/count', [
            'query' => ($params ?? new UsersListParams)->toQuery(),
        ]);

        return isset($payload['total_count']) && is_int($payload['total_count'])
            ? $payload['total_count']
            : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('POST', '/v1/users', [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey ?? Idempotency::keyFor($data),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $userId): array
    {
        return $this->transport->send('GET', '/v1/users/' . rawurlencode($userId));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $userId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->transport->send('PATCH', '/v1/users/' . rawurlencode($userId), [
            'body' => $data,
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function delete(string $userId): void
    {
        $this->transport->send('DELETE', '/v1/users/' . rawurlencode($userId));
    }

    /**
     * @return array<string, mixed>
     */
    public function ban(string $userId): array
    {
        return $this->transport->send('POST', '/v1/users/' . rawurlencode($userId) . '/ban');
    }

    /**
     * @return array<string, mixed>
     */
    public function unban(string $userId): array
    {
        return $this->transport->send('POST', '/v1/users/' . rawurlencode($userId) . '/unban');
    }

    /**
     * @return array<string, mixed>
     */
    public function lock(string $userId): array
    {
        return $this->transport->send('POST', '/v1/users/' . rawurlencode($userId) . '/lock');
    }

    /**
     * @return array<string, mixed>
     */
    public function unlock(string $userId): array
    {
        return $this->transport->send('POST', '/v1/users/' . rawurlencode($userId) . '/unlock');
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadProfileImage(string $userId, string $bytes, string $mime): array
    {
        $boundary = bin2hex(random_bytes(16));
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"profile_image\"\r\n"
            . "Content-Type: {$mime}\r\n"
            . "\r\n"
            . $bytes . "\r\n"
            . "--{$boundary}--\r\n";

        return $this->transport->send('POST', '/v1/users/' . rawurlencode($userId) . '/profile_image', [
            'rawBody' => $body,
            'contentType' => "multipart/form-data; boundary={$boundary}",
        ]);
    }

    public function deleteProfileImage(string $userId): void
    {
        $this->transport->send('DELETE', '/v1/users/' . rawurlencode($userId) . '/profile_image');
    }

    /**
     * @param  array<string, mixed>  $patch  any of public_metadata / private_metadata / unsafe_metadata
     * @return array<string, mixed>
     */
    public function updateMetadata(string $userId, array $patch): array
    {
        return $this->transport->send('PATCH', '/v1/users/' . rawurlencode($userId) . '/metadata', [
            'body' => $patch,
        ]);
    }

    public function verifyPassword(string $userId, string $password): bool
    {
        $payload = $this->transport->send(
            'POST',
            '/v1/users/' . rawurlencode($userId) . '/verify_password',
            ['body' => ['password' => $password]],
        );

        return isset($payload['verified']) && $payload['verified'] === true;
    }

    public function listSessions(string $userId): PaginatedList
    {
        $payload = $this->transport->send(
            'GET',
            '/v1/users/' . rawurlencode($userId) . '/sessions',
        );

        return PaginatedList::fromResponse($payload);
    }

    /**
     * Organizations land in v0.2 — the SDK surface exists today, it returns an empty
     * list until the BAPI ships the underlying endpoint.
     */
    public function listOrganizationMemberships(string $userId): PaginatedList
    {
        unset($userId);

        return PaginatedList::empty();
    }

    /**
     * Organizations land in v0.2 — see listOrganizationMemberships.
     */
    public function listOrganizationInvitations(string $userId): PaginatedList
    {
        unset($userId);

        return PaginatedList::empty();
    }

    /**
     * OAuth access tokens land in v0.4 — calling this against a v0.1 BAPI raises
     * a ResourceNotFoundException.
     *
     * @return array<string, mixed>
     */
    public function getOauthAccessToken(string $userId, string $provider): array
    {
        return $this->transport->send(
            'GET',
            '/v1/users/' . rawurlencode($userId) . '/oauth_access_tokens/' . rawurlencode($provider),
        );
    }
}
