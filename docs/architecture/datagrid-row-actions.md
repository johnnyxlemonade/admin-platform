# Řádkové akce DataGridu

Tento dokument doplňuje [DataGrid](datagrid.md) o kontrakt jedné akce nad řádkem. Řádková akce je prezentační volba; permission, aktuální stav entity a doménový invariant ověřuje vždy action handler a service na serveru.

## Definice a typy

`DataGridRowActionDefinition` serializuje `key`, `label`, `url`, `method`, volitelné `ConfirmationDefinition`, `modalUrl`, `modalSize`, `kind`, `placement`, `risk` a `refresh`. `ModuleActionPresentationFactory::rowAction()` sestavuje mutaci z registrované `ModuleActionDefinition`, ověří její permission a předá `refreshGrid` do `refresh`.

`DataGridRowActionKind` rozlišuje:

- `navigate` — odkaz na stránku; například Features v `ModulesDataGrid`.
- `modal` — odkaz s explicitními `modalUrl` a `modalSize`; například edit Jazyka a Oznámení.
- `mutation` — POST do `ModuleActionController`; typ používá factory pro registrované module actions.

Renderer zatím umí i kompatibilní fallback pro starší akci bez `kind`: GET vyhodnotí jako navigaci a POST jako mutaci, případně rozpozná `modalUrl`. Nový kód má typ uvádět explicitně, aby způsob provedení nezávisel na odvozování z URL nebo metody.

## Umístění, riziko a renderer

`DataGridRowActionPlacement` má `inline`, `primary`, `secondary` a `destructive`. `DataGridRowActionRisk` má `normal`, `warning` a `destructive`. `lemonade-datagrid.js` vykreslí inline akce jako popsaná tlačítka, primary jako ikonová tlačítka a ostatní do menu `…`. Destruktivní placement nebo risk oddělí v menu a zvýrazní červeně; warning je žlutý.

Řádek bez akcí nevytvoří trigger ani menu. Pokud grid deklaruje sloupec akcí kvůli jiným řádkům, buňka tohoto řádku zůstane prázdná. Audit jako read-only grid sloupec akcí nedeklaruje.

## Potvrzení a obnovení

`ConfirmationDefinition` poskytuje message key a volitelný title key pro `lemonade-confirm.js`. Používá se u state-changing akcí, které vyžadují potvrzení podle registrace modulu: Languages potvrzuje enable, disable a set-default; Notifications potvrzuje activate, deactivate, delete, restore a reset-display; lifecycle akcí Moduly jsou install, enable a disable. Potvrzení je ochrana UX, ne bezpečnostní hranice.

Mutation s `refresh !== false` dostane `data-lemonade-grid-refresh`; DataGrid po úspěšné action odpovědi obnoví data. Modal s `refresh: true` dostane `data-lemonade-modal-refresh-grid`; `lemonade-modal-form.js` po úspěšném uložení zavře shared Admin Modal a vyvolá refresh gridu. Validační chyba zůstává v otevřeném modalu.

## Příklady pravidel viditelnosti

`LanguagesDataGrid` nabízí edit modal s `modalSize: medium`. Disable a set-default zobrazuje jen pro zapnutý nevýchozí jazyk; enable jen pro vypnutý jazyk. `LanguageService` nezávisle vynucuje, že existuje právě jeden zapnutý výchozí jazyk a nelze jej vypnout.

`NotificationsDataGrid` nabízí edit modal s `modalSize: large`, lifecycle akce, reset-display a delete. Smazané oznámení má pouze restore. `NotificationLifecycleService` odmítne lifecycle změnu či reset smazaného záznamu; reset zobrazení nemění obsah oznámení.

`UsersDataGrid` a `RolesDataGrid` promítají serverem připravenou eligibility a stav soft-delete. `UserService` a `RoleService` zůstávají autoritou pro ochranu uživatele, posledního superadministrátora a systémových rolí. Skrytí akce v DataGridu není autorizace.
