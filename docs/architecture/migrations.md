# Migrace

## Globální registry

`MigrationDirectoryRegistrar` registruje explicitně určený PSR-4 adresář do frameworkového `MigrationRegistry`: načte pouze `*.php`, řadí soubory podle názvu, ověří existenci třídy a implementaci `MigrationInterface`. Admin platform migrations jsou vlastněné `src/Platform/Migrations` a další Admin capability registrují package-owned migrace z `src/Migrations` nebo z vlastního Admin module. Host composition nevlastni Admin schema migrations. Frameworkovy registry hlida duplicate identifier; identifikator urcuje metoda `identifier()` a urcuje poradi.

Admin platform migrations jsou rozdělené podle domény a zavislosti: `CreateCoreLanguages`, `CreateCoreUsers`, `CreateCoreUserIdentities`, `CreateCoreAuthorizationCatalog`, `CreateCoreAuthorizationAssignments`, `CreateCoreAuditLog`, `CreateCoreEditorLocks` a `CreateCoreCmsRoutes`. Admin capability migrace zahrnují `CreateNotifications` a `CreateDashboardWidgets`. Fresh schema patří do techto canonical migraci; patch migrace nejsou potreba pouze proto, aby upravily neexistujici predchozi fresh data.

## Composer moduly

Composer modul deklaruje vlastní migrace manifestem. `ModuleBootstrapServiceProvider`
je registruje přímo do frameworkového globálního `MigrationRegistry`; samostatný
module runner ani ledger neexistuje. `database:migrate` i databázový krok
instalátoru aplikují platformní, capability a modulové migrace v jednom
deterministickém pořadí. Běžný request migrace nespouští a odebrání Composer
balíčku nemaže jeho schema ani data.
