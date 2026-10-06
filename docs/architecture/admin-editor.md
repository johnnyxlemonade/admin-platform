# AdminEditor

## Deklarace a renderer

`AdminEditorBuilder` skládá immutable `AdminEditorDefinition`: vyžaduje definici formuláře a může obsahovat header, blocks, sections, tabs, sidebar, save bar a render metadata. `AdminEditorRenderer` vykresluje definici s `AdminEditorRenderContext`, který nese values, old input, errors a režim create/edit. Builder ani renderer neprovádějí business mutace.

Pole shared editoru tvoří `EditorDefinition`; `EditorDispatcher` z něj přijímá jen deklarovaná nezamčená pole. Před uložením ověří permission definice editoru, případné `EditorAccessPolicyInterface`, field permission a validační schema provideru. Neplatný vstup vrátí `EditorSaveOutcome::invalid`; uložené heslové pole se nevrací v safe input.

Pro serverem definovane selecty je `StaticSelectOptionSource`. Aktualni role v editoru Uzivatelu a filtry Uzivatelu/Auditu pouzivaji tento staticky zdroj. Asynchronni vyber prijemcu Oznameni je samostatny modulovy DOM a API contract, ne sdilena PHP option-source abstrakce.

## Zámky a konflikty

Při otevření standardního editoru `ModuleController::editorPage()` volá `EditorLockManager::acquire()`. `EditorDispatcher::save()` před mutací vyžaduje vlastnictví locku, pokud provider explicitně neumí bypass. U modalu načítá `EditorModalLoader`; obsazený záznam vrací 409 JSON. Modalní fragment se mountuje do shared native `<dialog>` Admin Modalu a po zavření se komponenty zničí, včetně release locku. Model Uživatelů používá verzi záznamu pro optimistic locking; `ModuleController` převádí konflikt na odpověď pro opětovné načtení.

## Konkrétní použití

- `LanguagesAdminEditorDefinitionFactory` sestavuje create/edit modal pro `code`, `name`, `flag_code` a `sort_order`. Ukládá jej `LanguagesEditor`, který předá data `LanguageService`.
- `NotificationsAdminEditorDefinitionFactory` používá fields `type`, `title`, `message` a audience custom block. `NotificationsEditor` volá `NotificationPublicationService`.
- `UsersAdminEditorDefinitionFactory` má standardní účetní pole a custom bloky pro identity a permission overrides.
- `RolesAdminEditorDefinitionFactory` přidává permissions custom block; `RolePermissionsRule` validuje zvolený katalog.

Jazyky ani Oznámení nemají pro tento flow standalone create/edit stránku; jejich DataGrid otvírá modal URL. Uživatelé a Role používají standardní editor stránky.
