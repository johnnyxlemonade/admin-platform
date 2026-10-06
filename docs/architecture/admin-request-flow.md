# Tok požadavku administrací

## Base path administrace

`AdminRoutingConfiguration` je hostem vlastněný kontrakt pro základní cestu administrace. Host jej registruje s hodnotou `/admin`; jde o příklad výchozí konfigurace, nikoli invariant Admin package. Jiný host může předat například `/backoffice` nebo `/management`.

`AdminRoutePrefixRegistrar` aplikuje tuto cestu jednou na registry `AdminRouteRegistrarInterface`. Jednotlivé Admin capability a externí moduly proto deklarují pouze relativní segmenty, například `news` nebo `api/datagrid/{module}`, a zachovávají své stávající named routes. Renderované endpointy a redirecty generují URL podle named routes, takže API i UI automaticky používají hostem zvolený prefix.

## HTML stránky

`AdminModuleRouteRegistrar` vede standardní administrační stránky do `ModuleController`. `ModuleController::index()` přeloží URL segment přes `AdminModuleRouteResolver`, ověří lifecycle pomocí `ModuleManager`, access policy a `ModulePageRegistry`; pak volá například `LanguagesModulePageProvider::index()`.

`ModuleController::create()` a `::edit()` obsluhují plnohodnotné editory. Pro editor používá `EditorDispatcher`, vytvoří nebo načte data a při editaci získá zámek přes `EditorLockManager`. Neexistující, nepovolený nebo nepodporovaný modul vrací administrační 404/403 HTML odpověď. Validace při POST znovu vykreslí stránku se stavem 422; při úspěchu následuje redirect.

## JSON transport

`DataGridController::index()` je endpoint pro tabulky. Kontroluje route, stav modulu, přístup k modulu, existenci gridu a `DataGridProviderInterface::permission()`. `DataGridQueryValidator` validuje query podle `DataGridDefinition`; úspěch vrací JSON se sloupci, filtry, řádky, řazením a stránkováním. Chybný filtr vrací 422 JSON, neznámý nebo nedostupný modul 404 JSON a zakázaný přístup 403 JSON.

`ModuleActionController` předává JSON payload do `ModuleActionDispatcher`. Dispatcher volá registrovaný handler, například `LanguagesSetEnabledAction` nebo `NotificationsResetDisplayAction`, a vrátí structured result. Konflikt zámku a optimistic conflict jsou 409 JSON; akce může vrátit `refreshGrid` nebo `refreshPage`.

Admin JSON transport používá `HttpStatusCode` a `AdminErrorCode`. Neplatný vstup vrací 422 `validation_failed`; permission nebo local-only capability vrací 403 (`permission_denied` nebo `local_actor_required`); chybějící či nepodporovaný cíl vrací 404; lock a stavový konflikt 409 `lock_conflict`. Úspěšná action zůstává 200 a struktura payloadu se nemění.

Zámek editoru je také shared interní transport: `POST api/editor/{module}/{id}/release` a `POST api/editor/cleanup`. Nejde o management stránku, proto endpoint nepatří pod `system/*`. Klient odvodí configurable Admin prefix z canonical editorové URL a volá tento API endpoint při opuštění editoru nebo zavření modalu.

## Modální editory

`EditorModalRouteRegistrar` poskytuje jediný shared GET transport: `api/modal/{module}/create` a `api/modal/{module}/{id}/edit`. Route parametr je Admin URL segment, který `EditorModalController` přeloží přes `AdminModuleRouteResolver` na runtime module code. Controller ověří lifecycle, přístup k modulu, registraci modalní presentation v `ModulePageRegistry` a podporu `create` nebo `save` v `ModuleActionRegistry`. `EditorDispatcher` pak authoritative ověří oprávnění editoru; pro editaci `EditorModalLoader` také získá lock. Odpověď má `modalHtml`.

Modul registruje pouze svou modalní presentation přes `ModuleModalEditorPageProviderInterface`; může tedy použít vlastní `AdminEditorDefinitionFactory` a namespaced view bez vlastního controlleru nebo route. `packages/admin/source/js/components/lemonade-modal-form.js` HTML vloží do vlastního native `<dialog>` Admin Modalu a volá `mountComponents()` pro vložený fragment.

Uložení stále používá shared module action transport. `lemonade-action.js` odesílá formulář, vykreslí validační chyby z JSON odpovědi a vyvolá `lemonade:action:success`. Modal listener jej zavře a při `refreshGrid: true` obnoví cílový DataGrid. `lemonade-confirm.js` zobrazuje potvrzení pro akce, jejichž `ModuleActionDefinition` obsahuje `ConfirmationDefinition`.
