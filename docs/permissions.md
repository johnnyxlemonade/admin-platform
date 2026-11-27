# Permissions

## Catalog and assignments

Booted Admin module definitions register `PermissionDefinition` objects in `PermissionCatalogRegistry`. `PermissionSyncService` projects the runtime catalog into the database but does not grant new permissions automatically and does not delete assignments for stale entries.

`AuthorizationService` combines role permissions from `system_role_permission` with user overrides from `system_user_permission`. An override may allow or deny a permission. A super-administrator role receives the complete runtime catalog.

`system.roles.*` manages role definitions and their permission sets. `system.users.manage_roles` assigns an existing role to a user, while `system.users.manage_permissions` manages individual user overrides. Service-layer safeguards protect system roles, super-administrator delegation, the current user, and the last active super administrator.

## Enforcement

Navigation permissions control visibility only. Controllers, `EditorDispatcher`, and `ModuleActionDispatcher` enforce the registered capability and permission for each request. Services enforce target-specific authorization, current state, and domain invariants. Hiding a navigation item or DataGrid action is never authorization.

Permission presentation may group module definitions into System, Modules, or Content Types. Presentation metadata does not change permission codes, dependencies, assignments, or server-side enforcement.
