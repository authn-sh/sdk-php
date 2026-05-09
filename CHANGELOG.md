# Changelog

## [Unreleased — v0.2]

### Added

- `OrganizationsManager` — `list`, `create`, `get`, `update`, `delete`, `members`, `invitations`, `domains`.
- `OrganizationMembershipsManager` — `list`, `create`, `update`, `delete`. Accessible via `$client->organizations()->members($orgId)`.
- `OrganizationInvitationsManager` — `list`, `create`, `bulkCreate`, `revoke`. Accessible via `$client->organizations()->invitations($orgId)`.
- `OrganizationDomainsManager` — `list`, `create`, `get`, `update`, `delete`, `verify`. Accessible via `$client->organizations()->domains($orgId)`.
- `Client::organizations()` accessor.
- `UsersManager::listOrganizationMemberships` and `listOrganizationInvitations` now hit the real BAPI endpoints.
- OpenAPI fixture refreshed to v0.2 bundle.

## [0.1.0] — 2025-05-03

Initial release: `UsersManager`, `SessionsManager`, `InvitationsManager`, `AllowlistIdentifiersManager`, `BlocklistIdentifiersManager`, `RedirectUrlsManager`, `InstanceManager`, `WebhookEndpointsManager`, `TokenVerifier`, `SignatureVerifier`.
