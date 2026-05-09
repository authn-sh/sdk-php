# Changelog

## [Unreleased — v0.2]

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
- `OrganizationDomainsManager` — `list`, `create`, `get`, `update`, `delete`, `verify`. Accessible via `$client->organizations()->domains($orgId)`.
- `Client::organizations()` accessor.
- `UsersManager::listOrganizationMemberships` and `listOrganizationInvitations` now hit the real BAPI endpoints.
- OpenAPI fixture refreshed to v0.2 bundle.

### Fixed

- `SystemPermissions`: replaced the incorrect v0.6 keys (`billing`, `sso`, `provisioning`) with the correct 13 v0.2 keys (`org:sys_profile:read/manage/delete`, `org:sys_memberships:read/manage`, `org:sys_invitations:read/manage`, `org:sys_domains:read/manage`, `org:sys_roles:read/manage`, `org:sys_permissions:read/manage`).

## [0.1.0] — 2025-05-03

Initial release: `UsersManager`, `SessionsManager`, `InvitationsManager`, `AllowlistIdentifiersManager`, `BlocklistIdentifiersManager`, `RedirectUrlsManager`, `InstanceManager`, `WebhookEndpointsManager`, `TokenVerifier`, `SignatureVerifier`.
