# Lemonade Admin Platform

[![PHPStan](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpstan.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpstan.yml)
[![Tests](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpunit.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpunit.yml)
[![Coding Standards](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/coding-standards.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](composer.json)

`johnnyxlemonade/admin-platform` contains the Admin HTTP runtime and built-in
System modules used by Lemonade applications. It runs on
`johnnyxlemonade/framework` and provides authentication and authorization,
module discovery and lifecycle management, navigation, DataGrid, AdminEditor,
dashboard widgets, audit logging, notifications, language and translation
management, shared file handling, and reusable JavaScript and CSS components.

## Built-in modules

The package declares these System manifests through Composer package metadata.
System modules are always installed and enabled; their routes and actions still
enforce their own permissions.

| Module | Code | Purpose |
| --- | --- | --- |
| Users | `system.users` | Local Admin accounts, role assignment and per-user permission overrides. |
| Roles | `system.roles` | Role catalogue and granular permission sets. |
| Languages | `system.languages` | `system_language` records and enabled/default state. |
| Translations | `system.translations` | Runtime overrides of source translations. |
| Notifications | `system.notifications` | Published Admin notifications and recipient display state. |
| Modules | `system.modules` | Discovered module state, lifecycle and configurable features. |
| Audit | `system.audit` | Read-only audit history. |
| Media | `system.media` | Read-only catalogue of shared `system_file` records. |

**Users** manages local accounts, including activation, soft deletion and restore.
Its editor assigns existing roles and per-user allow/deny overrides; effective
permissions combine role permissions with those overrides. Role catalogue
management remains in Roles. The Admin authentication runtime supports local
login and, when configured by the host, OIDC identity login; identity providers
are not managed by this module. User and role changes preserve security
invariants such as keeping a superadministrator available.

**Roles** manages role CRUD and each role's permission set. Permission
dependencies are resolved when a role is saved. System and superadministrator
roles are protected; a non-superadministrator cannot edit the superadministrator
role, delegate protected permissions, or remove the authority needed to manage
their own assigned role.

**Languages** manages `system_language` records. Languages can be created and
edited, enabled or disabled, and an enabled language can become the default.
The service preserves the invariant that exactly one enabled language is the
default, so the current default cannot simply be disabled.

**Translations** shows source and effective translation values by locale and
translation group, then stores only explicit runtime overrides. Overrides apply
to server translations and registered client translation groups. An override can
be reset to return to the source value; changes invalidate the corresponding
locale/group cache and revision used by client translation resources.

**Notifications** has two surfaces: a personal inbox with read/unread state and
an Admin management module. Managers publish notifications to a snapshot of
roles or users, activate/deactivate them, soft-delete/restore them, and can reset
recipient display state without changing notification content. The header dropdown
is a notification surface; the inbox and management module are authoritative.

**Modules** distinguishes a manifest discovered from Composer metadata from an
installed module state. A discovered optional module must be installed before it
can be enabled; its lifecycle also manages declared optional features and module
migrations. Missing manifests are retained as missing state rather than booted.
The module owns management UI and lifecycle permissions, while each module
manifest owns its code, provider, permissions, navigation metadata and optional
capabilities.

**Audit** is a read-only audit log for audited mutations and authentication
events, including local and OIDC login/logout. It also supplies audit
presentations and its own dashboard widgets; access to the log is
superadministrator-only.

**Media** is a read-only Admin view of shared `system_file` metadata and a file
download endpoint. The file uploader, rename/reorder/remove flows and audited
file mutations are shared Admin infrastructure for module-owned file fields.
The media catalogue is not a general deletion library. Image delivery and
variants belong to `johnnyxlemonade/admin-image`.

Dashboard is deliberately not listed in the table: it is not an
`AdminModuleDefinition`. The platform provides the dashboard route, widget
registry, per-user layout preferences and widget APIs. Modules and host packages
register their own widgets; this package does not claim host-specific content.

## Admin infrastructure

The package supplies the shared contracts and HTTP transport that built-in and
external Admin modules register into:

- Module manifests, discovery, runtime registries, lifecycle state and Admin
  metadata; navigation is generated from that metadata and permissions.
- Admin authentication, authorization and permission catalogues, including
  effective role and per-user permissions.
- DataGrid index transport, typed query validation, pagination, sorting,
  filters, row actions and CSV export.
- AdminEditor definitions and rendering, full-page and modal editors, optimistic
  editor locks, dirty-state handling and the shared module action dispatcher.
- Dashboard widget registration, access checks, personal pin/order/size
  preferences and widget content APIs.
- Confirmation dialogs, notifications, file upload UI and shared `system_file`
  mutation support, Lemonade Select, rich-text progressive enhancement, and
  client translation loading.

The JavaScript and CSS assets implement the browser side of these contracts,
including DataGrid refreshes, modal editor lifecycle, confirmations and the
Admin shell.

## Module routing

Admin module codes determine their management route family:

- `system.*` uses `/admin/system/{module}`.
- `cms.*` uses `/admin/cms/{module}`.
- Other modules use `/admin/{module}`.

`routeSegment` is the stable management segment, not a public CMS path. Public
CMS routing is separate. `/admin` is the usual host base path; the host can
configure a different base path.

## Adding a module

An additional Composer package declares its manifest class in
`extra.lemonade.modules`. Discovery reads Composer's generated installed-package
metadata, validates the manifest and boots its provider. The provider registers
its module definition and the capabilities it owns—such as pages, DataGrid,
AdminEditor, actions, permissions, navigation metadata, translations or
dashboard widgets—into the shared registries.

Optional modules are discovered first, then installed and explicitly enabled in
the Modules UI. A provider is not inferred by scanning directories, and a
duplicate module code is an error.

## Package boundaries

- `johnnyxlemonade/framework` owns the base application runtime and underlying
  infrastructure contracts.
- `johnnyxlemonade/cms-core` owns public CMS route runtime and CMS contracts;
  Admin Platform only provides Admin-side integration points.
- `johnnyxlemonade/admin-image` owns image delivery and image variant
  integration. Admin Platform owns shared file metadata and Admin file UI.
- `johnnyxlemonade/admin-cms-news` is a concrete optional CMS News module, not
  built-in Admin Platform functionality.

## Installation and host integration

```bash
composer require johnnyxlemonade/admin-platform
```

Register `Lemonade\Admin\AdminPackageServiceProvider` in the host application.
The host must also bind `AdminRoutingConfiguration` with its Admin base path
(commonly `/admin`), provide branding and asset configuration, and run the
application's migrations/installation flow. The provider registers platform
migrations and built-in manifests; discovery uses Composer metadata, so no
manual module list is required.

## Development and QA

Build changed source assets with:

```bash
npm install
npm run build
```

From the package root, run:

```bash
composer cs:check
composer stan
composer test
composer qa
```

This package does not version `composer.lock`; `composer.json` is the dependency
contract.
