# Oznámení jako architektonický příklad

## Složení

`NotificationsModuleProvider` registruje `NotificationsModuleDefinition`, `NotificationsDataGrid`, `NotificationsEditor`, index a modalní page provider, lifecycle actions, permission katalog, auditní prezentaci a `RegisterNotificationsModule`. Standardní tabulku poskytuje `NotificationsDataGrid`; create/edit trigger míří na shared `EditorModalController` přes `admin.api.modal.create` a `admin.api.modal.edit`.

`NotificationsAdminEditorDefinitionFactory` sestavuje modalní `AdminEditorDefinition` s typem, nadpisem, zprávou a audience blokem. `NotificationsEditor` validuje data a volá `NotificationPublicationService`. GET modal odpověď obsahuje `modalHtml`; POST create/save je registrovaná module action s `system.notifications.publish` a úspěch vrací refresh gridu.

## Data a service hranice

`admin_notification` drží typ, nadpis, zprávu, autora, aktivní stav a soft-delete. Publikum se snapshotuje do `admin_notification_audience_role` nebo `admin_notification_audience_user`; publikum musí být právě jednoho druhu. `admin_notification_recipient` drží recipient key, případnou vazbu na lokálního uživatele a `read_at`.

`NotificationPublicationService::publish()` ověří aktéra, permission, platnost a delegovatelnost zvolených rolí či cílových uživatelů, potom v jedné transakci uloží oznámení, snapshot publika a recipienty. `::update()` odmítne smazaný záznam, uloží text, nahradí publikum a resetuje display state před vytvořením aktuálních recipientů.

`NotificationLifecycleService` provádí activate, deactivate, soft-delete, restore a reset display. Reset zobrazení odmítá smazané oznámení a mění pouze recipient state, ne obsah. Každá skutečná mutace má `AuditOperation` a domain event. `NotificationsResetDisplayAction` je chráněná `system.notifications.reset_display`; její DataGrid akce má potvrzení.

## Typický tok úpravy

1. Row action otevře modal URL.
2. Controller načte data a lock, sestaví `modalHtml`.
3. Klient vloží fragment do shared native `<dialog>` Admin Modalu, inicializuje form a odešle module action.
4. Editor validuje vstup; service změní notification, snapshot publika a recipient state.
5. JSON response uzavře modal a obnoví DataGrid.
