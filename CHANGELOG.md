# Changelog

## [Unreleased — v0.2]

### Added

- `Organization` value object (`id`, `slug`, `role`, `permissions`) with `hasRole()` / `hasPermission()` helpers.
- `VerifiedClaims->organization` typed as `?Organization`, populated from JWT `org` claim. The deprecated `?array $org` alias is preserved for one minor version.
- `VerifiedClaims::hasRole(string $key)` / `::hasPermission(string $key)` convenience methods.
- `RolesManager` — `list`, `create`, `get`, `update`, `delete`, `setPermissions`. Accessible via `$client->roles()`.
- `PermissionsManager` — `list` (read-only). Accessible via `$client->permissions()`.
- `SystemPermissions` constants for all 12 system permission keys (e.g. `SystemPermissions::ORG_SYS_PROFILE_MANAGE`).
- `OrganizationsManager` — `list`, `create`, `get`, `update`, `delete`, `members`, `invitations`, `domains`.
- `OrganizationMembershipsManager` — `list`, `create`, `update`, `delete`. Accessible via `$client->organizations()->members($orgId)`.
- `OrganizationInvitationsManager` — `list`, `create`, `bulkCreate`, `revoke`. Accessible via `$client->organizations()->invitations($orgId)`.
- `OrganizationDomainsManager` — `list`, `create`, `get`, `update`, `delete`, `verify`. Accessible via `$client->organizations()->domains($orgId)`.
- `Client::organizations()` accessor.
- `UsersManager::listOrganizationMemberships` and `listOrganizationInvitations` now hit the real BAPI endpoints.
- OpenAPI fixture refreshed to v0.2 bundle.

## [0.1.0] — 2025-05-03

Initial release: `UsersManager`, `SessionsManager`, `InvitationsManager`, `AllowlistIdentifiersManager`, `BlocklistIdentifiersManager`, `RedirectUrlsManager`, `InstanceManager`, `WebhookEndpointsManager`, `TokenVerifier`, `SignatureVerifier`.
