# Changelog

## [0.5.0] — 2026-05-11

### Added

- `PasskeysManager` — BAPI binding for `/v1/passkeys` (`list`, `get`, `update(id, nickname)`, `delete`) plus the `Passkey` DTO and `PasskeysListParams` (with `user_id` filter). Accessible via `$client->passkeys()`. Enrollment is FAPI-only (browser ceremony) and is not surfaced here.
- `AppearanceManager` — BAPI binding for `/v1/instance/appearance` (`get`, `put`, `patch`) plus the `Appearance` DTO (`variables`, `elements`, `layout`). Accessible via `$client->appearance()`. `patch()` accepts a sparse array; the server deep-merges supplied keys.
- `LocalizationManager` — BAPI binding for `/v1/instance/localization` (`get`, `put`, `patch`) plus the `Localization` DTO (`default_locale`, `fallback_locale`, `supported_locales[]`, `overrides`). Accessible via `$client->localization()`. `patch()` supports the sparse per-key override merge: passing `null` for a key under `overrides[locale]` removes that single override.
- `VerifiedClaims->passkeyVerified: bool` parsed from the `pkv` JWT claim. `true` when the current session was authenticated by a passkey first-factor Challenge with `userVerification = required`. Defaults to `false` when the claim is absent.
- `VerifiedClaims->passkeyCount: int` parsed from the `pkc` JWT claim. Snapshot of the user's verified-passkey count at session creation. Defaults to `0` when the claim is absent or not a non-negative integer.
- `VerifiedClaims::wasVerifiedByPasskey()` and `::hasPasskey()` helper methods.

## [0.4.0] — 2026-05-11

### Added

- `OauthProvidersManager` — BAPI binding for `/v1/oauth-providers` (`list`, `create`, `get`, `update`, `delete`, `test`) plus `OauthProvider`, `OauthProviderTestResult`, `OauthProviderTestError` DTOs and the `OauthProvidersListParams`. Accessible via `$client->oauthProviders()`.
- `SmsTemplatesManager` — BAPI binding for `/v1/sms-templates` (`list`, `get`, `update(slug, payload)`, `revert(slug)`) plus the `SmsTemplate` DTO with `SLUG_*` constants. Accessible via `$client->smsTemplates()`.
- `Json::decodeAny()` and `Transport::sendAny()` for endpoints that return a top-level JSON array (`GET /v1/sms-templates`).
- `VerifiedClaims->phoneNumberVerified: bool` parsed from the `pnv` JWT claim. `false` when the claim is absent.
- `VerifiedClaims->defaultSecondFactor: ?string` parsed from the `dsf` JWT claim. One of `totp` / `phone_code` / `backup_code`; `null` for absent or unrecognized values.
- `VerifiedClaims::SECOND_FACTOR_TOTP` / `SECOND_FACTOR_PHONE_CODE` / `SECOND_FACTOR_BACKUP_CODE` constants.
- `VerifiedClaims::hasVerifiedPhoneNumber()` and `::preferredSecondFactor()` helper methods.

## [0.3.0] — 2026-05-10

### Added

- `UsersManager::verifyTotp(string $userId, string $code): TotpVerificationResult` — BAPI operator-driven TOTP check (does not stamp `TotpSecret.verified_at`).
- `UsersManager::disableMfa(string $userId): User` — BAPI operator nuke; clears `TotpSecret` + all `BackupCode` rows server-side and rehydrates the `User` shape.
- `VerifiedClaims->twoFactorVerified: bool`, `firstFactorAgeSeconds: ?int`, `secondFactorAgeSeconds: ?int` parsed from the `fva` JWT claim. `null` / `false` defaults when the claim is absent — no behavioural change for v0.2 callers.

## [0.2.0] — 2026-05-10

### Added

- `Organization` value object (`id`, `slug`, `role`, `permissions`) with `hasRole()` / `hasPermission()` helpers.
- `VerifiedClaims->organization` typed as `?Organization`, populated from JWT `org` claim. The deprecated `?array $org` alias is preserved for one minor version.
- `VerifiedClaims::hasRole(string $key)` / `::hasPermission(string $key)` convenience methods.
- `RolesManager` — `list`, `create`, `get`, `update`, `delete`, `setPermissions`. Accessible via `$client->roles()`.
- `PermissionsManager` — `list` (read-only). Accessible via `$client->permissions()`.
- `SystemPermissions` constants for all 13 system permission keys (e.g. `SystemPermissions::ORG_SYS_PROFILE_READ`).
- `OrganizationsManager` — `list`, `create`, `get`, `update`, `delete`, `members`, `invitations`, `domains`.
- `OrganizationMembershipsManager` — `list`, `create`, `update`, `delete`. Accessible via `$client->organizations()->members($orgId)`.
- `OrganizationInvitationsManager` — `list`, `create`, `bulkCreate`, `revoke`. Accessible via `$client->organizations()->invitations($orgId)`.
- `OrganizationDomainsManager` — `list`, `create`, `get`, `update`, `delete`. Accessible via `$client->organizations()->domains($orgId)`.
- `Client::organizations()` accessor.
- `UsersManager::listOrganizationMemberships` and `listOrganizationInvitations` now hit the real BAPI endpoints.
- OpenAPI fixture refreshed to v0.2 bundle (route slugs normalised to kebab-case, `WebhookEndpoint` gains `rotation_window_expires_at` / `disabled_at`, `Session` gains optional embedded `user` snapshot).

### Removed

- `OrganizationDomainsManager::verify()` — the `/verify` endpoint is superseded by the challenges flow (`/domains/{id}/challenges`).

### Fixed

- `SystemPermissions`: replaced the incorrect v0.6 keys (`billing`, `sso`, `provisioning`) with the correct 13 v0.2 keys (`org:sys_profile:read/manage/delete`, `org:sys_memberships:read/manage`, `org:sys_invitations:read/manage`, `org:sys_domains:read/manage`, `org:sys_roles:read/manage`, `org:sys_permissions:read/manage`).

## [0.1.0] — 2025-05-03

Initial release: `UsersManager`, `SessionsManager`, `InvitationsManager`, `AllowlistIdentifiersManager`, `BlocklistIdentifiersManager`, `RedirectUrlsManager`, `InstanceManager`, `WebhookEndpointsManager`, `TokenVerifier`, `SignatureVerifier`.
