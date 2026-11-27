# Migrations

`MigrationDirectoryRegistrar` registers an explicit PSR-4 migration directory in the framework `MigrationRegistry`. It loads PHP migration files, validates `MigrationInterface`, and relies on each migration's `identifier()` for deterministic ordering and duplicate detection.

Admin platform migrations live under `src/Platform/Migrations`. Admin capabilities and Composer modules may register package-owned migrations through the same global registry. The host does not own Admin schema migrations.

`database:migrate` and the installer database step execute platform, capability, and module migrations through one global ledger. Normal HTTP requests do not run migrations. Removing a Composer package does not delete its schema, data, or recorded migration identifiers.
