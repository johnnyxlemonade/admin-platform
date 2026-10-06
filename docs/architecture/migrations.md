# Migrace

## Globální registry

`MigrationDirectoryRegistrar` registruje explicitně určený PSR-4 adresář do frameworkového `MigrationRegistry`: načte pouze `*.php`, řadí soubory podle názvu, ověří existenci třídy a implementaci `MigrationInterface`. Admin platform migrations jsou vlastněné `src/Platform/Migrations` a další Admin capability registrují package-owned migrace z `src/Migrations` nebo z vlastního Admin module. Host composition nevlastni Admin schema migrations. Frameworkovy registry hlida duplicate identifier; identifikator urcuje metoda `identifier()` a urcuje poradi.

Admin platform migrations jsou rozdělené podle domény a zavislosti: `CreateCoreLanguages`, `CreateCoreModuleCatalog`, `CreateCoreModuleRoutePrefixes`, `CreateCoreUsers`, `CreateCoreUserIdentities`, `CreateCoreAuthorizationCatalog`, `CreateCoreAuthorizationAssignments`, `CreateCoreAuditLog`, `CreateCoreEditorLocks` a `CreateCoreCmsRoutes`. Admin capability migrace zahrnují `CreateNotifications` a `CreateDashboardWidgets`; builtin System moduly registrují své package-owned registrační migrace. Fresh schema patří do techto canonical migraci; patch migrace nejsou potreba pouze proto, aby upravily neexistujici predchozi fresh data.

## Systémové a optional moduly

Systémový provider registruje do globálního registry také svou registrační migraci, například `RegisterLanguagesModule`, `RegisterUsersModule`, `RegisterRolesModule`, `RegisterNotificationsModule`, `RegisterAuditModule` nebo `RegisterModulesModule`. Tyto migrace vytvářejí registry metadata modulů.

Optional modul deklaruje vlastní migrace manifestem. `ModuleMigrationRunner` vytvoří pro jejich běh samostatný registry, ale používá stejný globální migration ledger. `database:migrate` aplikuje Admin platform, Admin capability a builtin System migrations. `modules:migrate` aplikuje pending migrace instalovaných optional modulu vcetne vypnutych; instalace optional modulu aplikuje jeho pending migrace pred vytvorenim lifecycle radku. Běžný request migrace ani discovery nespouští.
