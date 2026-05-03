<?php

declare(strict_types=1);

/**
 * Light-weight contract test against the bundled OpenAPI spec.
 *
 * Looks for `tests/fixtures/openapi.bundled.json`. If it isn't present, or its
 * `paths` object is empty (the v0.1 skeleton hasn't had paths land yet), the
 * test is marked skipped so it doesn't block CI. Once OA-2/OA-3/OA-4 ship and
 * the fixture is refreshed, the test automatically activates.
 */
it('declares operations for every BAPI endpoint the SDK calls', function (): void {
    $fixture = __DIR__ . '/fixtures/openapi.bundled.json';

    if (! is_file($fixture)) {
        $this->markTestSkipped('No openapi.bundled.json fixture present yet.');
    }

    /** @var array<string, mixed> $spec */
    $spec = json_decode((string) file_get_contents($fixture), true, flags: JSON_THROW_ON_ERROR);
    $paths = is_array($spec['paths'] ?? null) ? $spec['paths'] : [];

    if ($paths === []) {
        $this->markTestSkipped('Bundled OpenAPI has no paths yet (OA-2/3/4 not landed).');
    }

    $expected = [
        ['GET', '/v1/users'],
        ['POST', '/v1/users'],
        ['GET', '/v1/users/count'],
        ['GET', '/v1/users/{user_id}'],
        ['PATCH', '/v1/users/{user_id}'],
        ['DELETE', '/v1/users/{user_id}'],
        ['POST', '/v1/users/{user_id}/ban'],
        ['POST', '/v1/users/{user_id}/unban'],
        ['POST', '/v1/users/{user_id}/lock'],
        ['POST', '/v1/users/{user_id}/unlock'],
        ['POST', '/v1/users/{user_id}/profile_image'],
        ['DELETE', '/v1/users/{user_id}/profile_image'],
        ['PATCH', '/v1/users/{user_id}/metadata'],
        ['POST', '/v1/users/{user_id}/verify_password'],
        ['GET', '/v1/sessions'],
        ['GET', '/v1/sessions/{session_id}'],
        ['POST', '/v1/sessions/{session_id}/revoke'],
        ['GET', '/v1/invitations'],
        ['POST', '/v1/invitations'],
        ['POST', '/v1/invitations/{invitation_id}/revoke'],
        ['GET', '/v1/allowlist_identifiers'],
        ['POST', '/v1/allowlist_identifiers'],
        ['DELETE', '/v1/allowlist_identifiers/{identifier_id}'],
        ['GET', '/v1/blocklist_identifiers'],
        ['POST', '/v1/blocklist_identifiers'],
        ['DELETE', '/v1/blocklist_identifiers/{identifier_id}'],
        ['GET', '/v1/redirect_urls'],
        ['POST', '/v1/redirect_urls'],
        ['GET', '/v1/redirect_urls/{redirect_url_id}'],
        ['DELETE', '/v1/redirect_urls/{redirect_url_id}'],
        ['GET', '/v1/instance'],
        ['PATCH', '/v1/instance'],
        ['PATCH', '/v1/instance/restrictions'],
    ];

    foreach ($expected as [$method, $path]) {
        expect($paths)->toHaveKey($path, "Expected path {$path} to be documented in the OpenAPI spec.");
        $verbs = is_array($paths[$path] ?? null) ? array_change_key_case($paths[$path], CASE_LOWER) : [];
        expect($verbs)->toHaveKey(strtolower($method), "Expected {$method} on {$path}.");
    }
});
