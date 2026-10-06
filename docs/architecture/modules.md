# Moduly

## Mapa modulových subsystémů

`packages/admin/src/Modules` není jedna registry vrstva. Obsahuje oddělené kontrakty pro deklaraci dostupného kódu, jeho discovery, bootování, lifecycle stav uložený v databázi a volitelné capability modulu. Tyto pojmy se nepoužívají zaměnitelně.

### Manifest a deklarace modulu

`ModuleManifestInterface`, `ModuleManifestDefinition` a `ModuleKind` popisují, co modul deklaruje: jeho kód, druh, runtime provider a lokalizační klíč. Manifest je deklarace kódu nalezená discovery; neobsahuje installed ani enabled stav z databáze.

### Catalog a discovery

`ModuleCatalog` při běžném bootstrapu jednou načte Composerem vygenerované `vendor/composer/installed.json` a z `extra.lemonade.modules` sestaví dostupné manifesty. Discovery pouze čte Composer metadata: neprochází filesystem, nezapisuje do databáze a nespouští migration ani synchronizaci oprávnění. Katalog určuje, který modulový kód je v aplikaci dostupný; není to runtime registry bootnutých modulů ani databázový lifecycle stav.

### Runtime registry

`ModuleRegistry` obsahuje definice typu `ModuleDefinitionInterface` aktivně zaregistrované providerem během bootování aplikace. Je určen pro runtime capability lookup. Registrace v `ModuleRegistry` tedy znamená, že provider modulu pro aktuální běh zaregistroval svou definici; sama o sobě neurčuje, zda optional modul má lifecycle řádek v databázi.

### Lifecycle a databázový stav

`ModuleState` a `ModuleStateResolver` skládají deklarovaný manifest se stavem `system_module`. `ModuleLifecycleService` provádí install, enable, disable a migraci optional modulů. Tabulka `system_module` drží installed/enabled metadata; systémový manifest je vždy installed a enabled, zatímco optional modul vyžaduje objevený manifest i odpovídající lifecycle řádek.

### Features

`ModuleFeatureDefinition` deklaruje feature v manifestu včetně její kategorie a invariantů. `ModuleFeatureState` a `ModuleFeatureStateResolver` odvozují její configured a effective stav z deklarace a `system_module_feature`. `ModuleFeatureLifecycleService` vytváří chybějící toggle rows, synchronizuje je a provádí autorizované auditované změny. Deklarovaná feature proto není totéž co její konfigurovaná databázová hodnota; required capability zůstává pouze ke čtení.

### Public route prefixy

`ModulePublicRoutePrefixDefinition` a `ModulePublicRoutePrefixManifestInterface` deklarují lokalizované prefixy veřejných rout modulu. `ModuleRoutePrefixLifecycleService` je inicializuje a synchronizuje do `system_module_route_prefix`. Jde o modulovou public/CMS routing infrastrukturu, nikoli o administrativní UI nebo sidebar navigaci.

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

`ModuleManifestDiscovery` čte deklarace z Composerem generovaného `vendor/composer/installed.json`, mezi kterými jsou i builtin manifesty aktuálního `packages/admin` balíčku. Neprochází adresář `vendor` ani nenačítá `composer.json` balíčků podle filesystemu. Composer autoload je authority pro načtení deklarované classy. Všechny deklarace procházejí stejnou validací manifestu, provideru, features a public route prefixu; duplicitní module code proto selže bez precedence zdroje. `modules:discover` může zapsat seřazený snapshot do `storage/cache/modules.php` pro diagnostiku či explicitní warmup, ale runtime na něm nezávisí. Za běžného requestu `ModuleBootstrapServiceProvider` metadata načte jednou přes containerový `ModuleCatalog`; neprovádí filesystem scan ani zápis.

`ModuleStateResolver` spojuje manifest s lifecycle metadaty. Systémový manifest je vždy nainstalovaný a zapnutý. Optional modul je nejdříve discovered; po instalaci má řádek `system_module` a je disabled, dokud jej uživatel explicitně nezapne. Osiřelý databázový řádek bez manifestu zůstává zachovaný a v management UI se zobrazí jako missing; runtime jej neregistruje. CMS content modul je optional modul, který navíc vlastní obsahový model a veřejný content lifecycle; jeho rozpracované implementace nejsou součástí této dokumentace.

`system_module` drží registry/lifecycle metadata. `system_module_feature` obsahuje konfigurovaný stav nepovinných toggle funkcí a `system_module_route_prefix` lokalizované veřejné prefixy. `ModuleFeatureLifecycleService` přijímá pouze funkci deklarovanou manifestem pro nainstalovaný optional modul. Required, Admin a strukturální capability jsou pouze ke čtení; vypnutý modul může změnit configured toggle, jeho effective stav ale zůstává vypnutý.

## Provider a capability

Provider modulu se registruje přes `ModuleBootstrapServiceProvider`. Například `LanguagesModuleProvider` registruje `LanguagesModuleDefinition` do `ModuleRegistry` a `AdminModuleRegistry`, `LanguagesDataGrid` do `DataGridRegistry`, `LanguagesEditor` do `EditorRegistry`, index page provider, action registrar, překlady, views, permission definitions a registrační migraci.

Sdilene Admin registry pripravuji capability providery pred `ModuleBootstrapServiceProvider`: `AdminModuleTransportServiceProvider` vlastni DataGrid, action a page registry, `AdminEditorServiceProvider` editor registry a `AdminNotificationServiceProvider` globalni notifikacni sluzby pouzivane systemovym modulem Notifications. Root `AdminServiceProvider` drzi pouze cross-module infrastrukturu. `ModuleBootstrapServiceProvider::requires()` tyto providery uvadi explicitne, protoze module providery do jejich registru zapisuji uz behem bootstrapu.

`AdminModuleMetadata` určuje kód, ikonu, skupinu, pořadí, cílovou route a navigation permission. `AdminNavigation` z těchto metadat sestavuje sidebar. `ModulePageRegistry` poskytuje oddělené indexové a full-page editorové capability; editor se registruje také do `EditorRegistry`. Read-only modul Audit registruje index a DataGrid, ne prázdný editor.

## Oprávnění a audity

`*ModuleDefinition::permissionDefinitions()` poskytuje runtime katalog `PermissionDefinition`. Provider jej předá `PermissionCatalogRegistry`. Mutační service, například `LanguageService` nebo `ModuleFeatureLifecycleService`, vlastní `AuditOperation` a provede změnu přes `TransactionalEventProcessor`; DataGrid ani editor auditní metadata nenesou.

## Typický tok registrace

1. Manifest určí kód, druh a runtime provider.
2. Bootstrap načte katalog a zavolá runtime provider kazdeho jeho manifestu.
3. Provider zaregistruje konkrétní capability do shared registrů.
4. Generic admin routes najdou capability podle kódu modulu; nevytvářejí vlastní route sadu pro každý standardní index, editor nebo action.
