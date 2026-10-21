# Moduly

## Zdroj pravdy

Composer instalace aplikace je jediným zdrojem pravdy pro dostupnost modulů.
Balíček deklaruje manifesty v `extra.lemonade.modules`; `ModuleCatalog` je při
bootstrapu načte z Composerem generovaného `vendor/composer/installed.json`.
Modul nalezený v těchto metadatech je součástí aplikace a je aktivní. Odebraný
Composer balíček se nebootuje a jeho capability nejsou dostupné.

Discovery neprochází filesystem, nezapisuje do databáze, nespouští migrace a
nevytváří persistentní cache. Katalog drží pouze in-memory snapshot pro jeden
application bootstrap. Chybějící `installed.json`, neautoloadovatelný manifest,
neplatný provider nebo duplicitní module code ukončí bootstrap jasnou chybou.

`AvailableModule` spojuje manifest s názvem, verzí a popisem vlastnického
Composer balíčku. Tato projekce slouží read-only stránce `Admin → Moduly`; není
dalším stavovým registrem.

## Manifest a bootstrap

`ModuleManifestInterface` deklaruje stabilní code, lokalizační klíč názvu a
runtime provider. `ModuleBootstrapServiceProvider` zaregistruje provider každého
dostupného manifestu před container freeze a po freeze spustí jeho runtime
contributions.

Manifest implementující `AdminModuleDefinitionInterface` automaticky přispívá
Admin metadata, permission definice a auditní prezentace. Provider registruje
konkrétní capability, například DataGrid, editor, page provider, actions,
resources, dashboard widget nebo veřejný routing. Registry proto obsahují jen
capability modulů přítomných v Composer instalaci; další installed/enabled stav
se neudržuje.

`AdminModuleRegistry` je runtime registry administrativních metadat bootnutých
modulů. Není katalogem dostupnosti: její obsah už je důsledkem Composer
discovery. Generic Admin transport vrací 404, pokud route segment nebo požadovaná
capability nejsou registrované, a nadále samostatně kontroluje authorization.

## Migrace

Manifest může implementovat `ModuleMigrationManifestInterface` a deklarovat své
migrace. Bootstrap je registruje do stejného frameworkového `MigrationRegistry`
jako platformní migrace. Standardní `database:migrate` a databázový krok
instalátoru proto aplikují všechny pending migrace dostupných Composer modulů ve
společném globálním ledgeru a deterministickém pořadí identifierů.

Běžný HTTP request migrace nespouští. Přidání balíčku vyžaduje explicitní
instalační nebo deployment migration krok. Odebrání balíčku jeho databázová data
automaticky nemaže a orphaned identifier může zůstat v globálním ledgeru.

## Permissions a navigace

Permissions se registrují výhradně z bootnutých manifestů a providerů. Při
instalaci nebo deploymentu `PermissionSyncService` synchronizuje databázový
katalog s runtime definicemi a reportuje stale uložené záznamy bez mazání jejich
assignment vazeb. Nově nalezené permission pouze zpřístupní pro následné ruční
přidělení; synchronizace je automaticky nepřidává žádnému uživateli ani roli.

Editory rolí a uživatelů filtrují databázovou projekci přes runtime
`PermissionCatalogRegistry`, takže oprávnění odebraného Composer modulu
nezobrazí. Permission dependencies a existující
authorization mechanismus zůstávají beze změny.

Navigace, routy, DataGridy, editory, actions, widgety a veřejné CMS handlery se
registrují pouze při bootstrapu dostupného modulu. Jejich viditelnost a použití
nadále vyžadují příslušná uživatelská oprávnění; Composer dostupnost sama žádné
oprávnění neuděluje.

## Read-only přehled

Systémový modul `system.modules` zobrazuje pouze Composerem dostupné moduly:
lokalizovaný název, code, verzi balíčku a jeho popis. Nemá install, enable ani
disable actions a deklaruje pouze permission `system.modules.view`.
