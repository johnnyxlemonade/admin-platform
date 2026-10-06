# DataGrid

`DataGridProviderInterface` poskytuje `DataGridDefinition`, execute metodu a permission. Definice obsahuje immutable `DataGridColumnDefinition`, `DataGridFilterDefinition`, podporované sort keys, výchozí pohled a stránkování. `DataGridController` ji serializuje do JSON; stav gridu na klientovi je per-module v localStorage, zatímco HTML URL indexu zůstává čistá.

## Razeni

DataGrid API prijima explicitni dvojici `sort=<key>&direction=<asc|desc>`. `DataGridQueryValidator` povoli jen sort keys deklarovane definici a smer `asc` nebo `desc`. Klientsky per-module stav uklada sort key a direction jako dve oddelene hodnoty; contract nepouziva kodovani sestupneho razeni ve tvaru `sort=-key`.

## Filtry a řádky

`LanguagesDataGrid` zobrazuje název, kód, vlajku, default, stav a pořadí. `UsersDataGrid` používá stav Aktivní, Neaktivní a Smazané. `NotificationsDataGrid` nabízí Všechny, Aktivní, Neaktivní a Smazané a zobrazuje typ, nadpis, autora, publikum, stav a datum. `RolesDataGrid` rozlišuje Aktivní a Smazané; `AuditDataGrid` je read-only.

Provider vytvoří `DataGridRowDefinition` a typed cells, včetně `StatusCell`. Dotaz, řazení a stránkování zpracuje model nebo service daného modulu; například `LanguageService::listForDataGrid()` deleguje do `LanguageModel`.

## Řádkové akce

`ModuleActionPresentationFactory` promítá registrovanou `ModuleActionDefinition` do viditelné row action. Viditelnost je jen UX: handler a service znovu ověřují permission, stav a business invariant. `ConfirmationDefinition` řídí klientské potvrzení; mutační akce odpovídají shared JSON transportem.

Příklady:

- `LanguagesDataGrid` nabízí edit modal, enable/disable a set-default. Disable a set-default se ukazují jen tam, kde je akce smysluplná; `LanguageService` přesto vynucuje jeden aktivní default.
- `NotificationsDataGrid` nabízí edit modal, activate/deactivate, reset-display, delete a restore. `reset-display` používá `system.notifications.reset_display` a nemění text oznámení.
- `UsersDataGrid` skrývá nepřípustnou deaktivaci či smazání podle `action_eligibility`; `UserService` je autorita pro ochranná pravidla.
- `RolesDataGrid` nabizi smazani a obnoveni jen pro custom roli, ne systemovou ani superadmin roli.

Navigační akce vedou na editor, modal action nese modal URL a mutace projde `ModuleActionDispatcher`. Po úspěšné modal mutaci `refreshGrid: true` obnoví tabulku; validační chyba nechá modal otevřený.

Podrobný kontrakt `DataGridRowActionDefinition`, včetně typů, umístění a rendereru, popisují [Řádkové akce DataGridu](datagrid-row-actions.md).
