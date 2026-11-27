# Lemonade Admin Platform

[![Tests](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/admin-platform/phpunit.yml?branch=main&label=Tests&logo=githubactions&logoColor=white&labelColor=0f766e)](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpunit.yml)
[![PHP 8.3–8.6](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5%20%7C%208.6-777BB4?logo=php&logoColor=white)](#requirements)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/admin-platform/phpstan.yml?branch=main&label=PHPStan&logo=php&logoColor=white&labelColor=5b4baf)](https://github.com/johnnyxlemonade/admin-platform/actions/workflows/phpstan.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE)

`johnnyxlemonade/admin-platform` provides the reusable, server-rendered administration runtime for Lemonade applications. It includes authentication, authorization, Composer module discovery, navigation, DataGrid, AdminEditor, dashboard widgets, audit logging, notifications, file handling, and shared browser components.

## Built-in modules

Composer metadata declares the built-in System modules. A module present in the Composer installation is available at runtime; its routes and actions continue to enforce their own permissions.

| Module | Identifier | Capability |
| :--- | :--- | :--- |
| **Audit** | `system.audit` | Readonly audit history |
| **Languages** | `system.languages` | Administration languages and default locale |
| **Media** | `system.media` | Readonly shared-file catalog |
| **Modules** | `system.modules` | Readonly Composer module overview |
| **Notifications** | `system.notifications` | Admin notifications and recipient state |
| **Roles** | `system.roles` | Roles and permission sets |
| **Translations** | `system.translations` | Runtime translation overrides |
| **Users** | `system.users` | Accounts, role assignment, and permission overrides |

Dashboard is platform infrastructure rather than an `AdminModuleDefinition`. Modules and host packages register their widgets in the shared dashboard registry.

## Requirements

- PHP 8.3 or newer; CI verifies PHP 8.3, 8.4, 8.5, and 8.6
- PHP extensions `fileinfo`, `mbstring`, `openssl`, and `PDO`
- `johnnyxlemonade/framework`

## Installation

```sh
composer require johnnyxlemonade/admin-platform
```

Register `Lemonade\Admin\AdminPackageServiceProvider` in the host application. The host also provides its Admin base path, branding, and asset configuration, then runs its normal migration and permission-sync deployment flow. Composer metadata discovers module manifests; no separate module list or enabled state is required.

Publish a prebuilt Admin asset distribution into the host public root with:

```sh
php vendor/bin/lemonade admin:assets:publish
```

See [Admin assets](docs/admin-assets.md) for the build and publishing contract.

## Adding a module

An external Composer package declares its manifest in `extra.lemonade.modules`. Its runtime provider registers the capabilities it owns, such as pages, DataGrid, AdminEditor, actions, permissions, translations, migrations, or dashboard widgets.

Composer availability activates the module. Adding a package requires the host's controlled migration and permission-sync steps; removing it stops runtime registration without deleting its stored data.

## Package boundaries

- `johnnyxlemonade/framework` owns the base application runtime and infrastructure contracts.
- `johnnyxlemonade/cms-core` owns public CMS routing and shared content contracts.
- `johnnyxlemonade/admin-image` owns image delivery and image variants.
- Admin Platform owns Admin transport, shared presentation, authorization, audit, and file-management UI.
- Host applications own composition, branding, and public rendering.

## Documentation

- [Admin assets](docs/admin-assets.md) — package build output and host publishing
- [AdminEditor](docs/admin-editor.md) — editor definitions, validation, and locks
- [Admin request flow](docs/admin-request-flow.md) — HTML pages, JSON endpoints, and modal transport
- [DataGrid](docs/datagrid.md) — query contract, rows, and actions
- [Identity](docs/identity.md) — local and OIDC authentication
- [Migrations](docs/migrations.md) — global migration registration
- [Modules](docs/modules.md) — Composer discovery and runtime registration
- [Permissions](docs/permissions.md) — permission catalog, assignments, and enforcement
- [Self-service user profile](docs/user-profile.md) — current-user profile functionality

## Development and QA

Build Admin assets after changing JavaScript or SCSS:

```sh
npm ci
npm run build
```

Run the package checks with:

```sh
composer qa
```
