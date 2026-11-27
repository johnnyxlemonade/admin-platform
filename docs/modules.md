# Modules

## Discovery

The application's Composer installation is the source of truth for available modules. Packages declare manifests in `extra.lemonade.modules`; `ModuleCatalog` reads them from Composer's generated `vendor/composer/installed.json` during bootstrap.

Discovery does not scan the filesystem, write module state to the database, run migrations, or create a persistent module cache. A missing or invalid manifest, provider, or duplicate module code fails bootstrap explicitly.

## Runtime registration

`ModuleManifestInterface` declares a stable module code, label key, and runtime provider. `ModuleBootstrapServiceProvider` registers each provider before the container is frozen and runs bootable contributions afterward.

Admin module manifests contribute metadata, permission definitions, and audit presentation. Runtime providers register concrete capabilities such as DataGrids, editors, page providers, actions, resources, widgets, file usage, or public routes. Registries therefore contain only capabilities from Composer-installed modules.

Module migrations join the framework's global `MigrationRegistry`. Adding a package requires the normal deployment migration step; removing it does not delete its database data.

Permissions and navigation remain independent concerns: installing a module makes its capabilities discoverable but grants no user permission.

The `system.modules` page is a readonly view of Composer-discovered modules. It does not maintain a separate installed or enabled state.
