# Moduly

## Mapa modulových subsystémů

`packages/admin/src/Modules` není jedna registry vrstva. Obsahuje oddělené kontrakty pro deklaraci dostupného kódu, jeho discovery, bootování, lifecycle stav uložený v databázi a volitelné capability modulu. Tyto pojmy se nepoužívají zaměnitelně.

### Manifest a deklarace modulu

`ModuleManifestInterface`, `ModuleManifestDefinition` a `ModuleKind` popisují, co modul deklaruje: jeho kód, druh, runtime provider a lokalizační klíč. Manifest je deklarace kódu nalezená discovery; neobsahuje installed ani enabled stav z databáze.

### Catalog a discovery

`ModuleCatalog` při běžném bootstrapu jednou načte Composerem vygenerované `vendor/composer/installed.json` a z `extra.lemonade.modules` sestaví dostupné manifesty. Discovery pouze čte Composer metadata: neprochází filesystem, nezapisuje do databáze a nespouští migration ani synchronizaci oprávnění. Katalog určuje, který modulový kód je v aplikaci dostupný; není to runtime registry bootnutých modulů ani databázový lifecycle stav.

### Admin metadata a runtime capability

`AdminModuleRegistry` obsahuje pouze administracni metadata aktivne bootnutych
modulu a overuje jedinecnost jejich kodu a URL segmentu. Dostupnost kodu urcuje
`ModuleCatalog`, databazovy lifecycle `ModuleStateResolver` a konkretni
DataGrid, editor, page nebo action capability maji vlastni existujici registry.
Paralelni obecna module registry se neudrzuje.

### Lifecycle a databázový stav

`ModuleState` a `ModuleStateResolver` skládají deklarovaný manifest se stavem `system_module`. `ModuleLifecycleService` provádí install, enable, disable a migraci optional modulů. Tabulka `system_module` drží installed/enabled metadata; systémový manifest je vždy installed a enabled, zatímco optional modul vyžaduje objevený manifest i odpovídající lifecycle řádek.

### Optional modulové migrace

`ModuleMigrationManifestInterface` deklaruje migrace optional modulu. `ModuleMigrationRunner` je spouští v samostatném migration registry, ale proti společnému globálnímu migration ledgeru a s kontrolou identifier unikátnosti. Liší se tím od globálních baseline migrací Core, Admin a systémových modulů, které aplikuje běžný `database:migrate`.

## Discovery a lifecycle

Manifest modulu pochází z Composer package metadata:

Samostatně instalovaný Composer package deklaruje manifest class ve svém `composer.json`:

```json
{
  "extra": {
    "lemonade": {
      "modules": [
        "Lemonade\\Cms\\News\\ModuleManifest"
      ]
    }
  }
}
```

`ModuleManifestDiscovery` čte deklarace výhradně z Composerem generovaného `vendor/composer/installed.json`, mezi kterými jsou i builtin manifesty aktuálního `packages/admin` balíčku. Neprochází adresář `vendor`, nenačítá jednotlivé `composer.json` balíčků podle filesystemu a nevytváří vlastní diskový katalog. Composer autoload je authority pro načtení deklarované classy. Všechny deklarace procházejí stejnou validací manifestu a runtime provideru; duplicitní module code proto selže bez precedence zdroje. `ModuleCatalog` metadata načte jednou během application bootstrapu a výsledek drží pouze v paměti. Chybějící `vendor/composer/installed.json` znamená nekompletní Composer instalaci a bootstrap skončí jasnou chybou.

`ModuleStateResolver` spojuje manifest s lifecycle metadaty. Systémový manifest je vždy nainstalovaný a zapnutý. Optional modul je nejdříve discovered; po instalaci má řádek `system_module` a je disabled, dokud jej uživatel explicitně nezapne. Osiřelý databázový řádek bez manifestu zůstává zachovaný a v management UI se zobrazí jako missing; runtime jej neregistruje. CMS content modul je optional modul, který navíc vlastní obsahový model a veřejný content lifecycle; jeho rozpracované implementace nejsou součástí této dokumentace.

`system_module` drží registry/lifecycle metadata. Instalace optional modulu aplikuje jeho migrace, synchronizuje permissions a vytvoří disabled lifecycle řádek. Zapnutí a vypnutí mění pouze tento lifecycle stav; veřejné routy a další capability vlastní přímo příslušný modul.

## Provider a capability

Provider modulu se registruje pres `ModuleBootstrapServiceProvider`. Manifest,
ktery implementuje `AdminModuleDefinitionInterface`, je canonical deklaraci kodu,
Admin metadat, permissions a auditnich prezentaci; bootstrap je z nej registruje
automaticky. Implementuje-li take `AdminModuleFileCapabilitiesInterface`, stejny
bootstrap rozvine jeho file usages. Provider explicitne registruje jen konkretni
capability, napriklad `DataGridRegistry`, `EditorRegistry`, page providery, actions,
resources a verejny routing.

Systemove moduly mohou nadale pouzivat samostatnou `*ModuleDefinition`, pokud jejich
builtin manifest tuto Admin deklaraci nevlastni. I tyto definice se registruji pouze
do `AdminModuleRegistry`; dostupnost jejich kodu stale pochazi z manifest catalogu.

Sdilene Admin registry pripravuji capability providery pred `ModuleBootstrapServiceProvider`: `AdminModuleTransportServiceProvider` vlastni DataGrid, action a page registry, `AdminEditorServiceProvider` editor registry a `AdminNotificationServiceProvider` globalni notifikacni sluzby pouzivane systemovym modulem Notifications. Root `AdminServiceProvider` drzi pouze cross-module infrastrukturu. `ModuleBootstrapServiceProvider::requires()` tyto providery uvadi explicitne, protoze module providery do jejich registru zapisuji uz behem bootstrapu.

`AdminModuleMetadata` určuje kód, ikonu, skupinu, pořadí, cílovou route a navigation permission. `AdminNavigation` z těchto metadat sestavuje sidebar. `ModulePageRegistry` poskytuje oddělené indexové a full-page editorové capability; editor se registruje také do `EditorRegistry`. Read-only modul Audit registruje index a DataGrid, ne prázdný editor.

## Oprávnění a audity

`AdminModuleDefinitionInterface::permissionDefinitions()` poskytuje runtime katalog
`PermissionDefinition`. U Admin manifestu jej do `PermissionCatalogRegistry`
preda bootstrap. Mutacni service provede zmenu pres
`TransactionalEventProcessor`, kteremu preda pouze `AuditActor`; explicitni
`DomainEvent` nese kod modulu a auditni udalosti. Audit se zapisuje ve stejne
transakci a sekundarni eventy se dispatchuji az po commitu.

## Typický tok registrace

1. Manifest určí kód, druh, runtime provider a případné Admin metadata.
2. Bootstrap načte katalog, registruje deklarované základní Admin contributions a zavolá runtime provider každého manifestu.
3. Provider zaregistruje konkrétní capability do shared registrů.
4. Generic admin routes najdou capability podle kódu modulu; nevytvářejí vlastní route sadu pro každý standardní index, editor nebo action.
