# Architektura aplikace

Admin je server-rendered reusable backoffice a module platforma v balicku `packages/admin` s namespace `Lemonade\\Admin`. Vlastni transporty, prezentaci, auth runtime, autorizaci, audit, identity, module catalog/discovery/bootstrap/lifecycle, platform migrations i builtin System moduly. Host poskytuje composition a `app/Modules` jako canonical root pro project-local moduly.

## Dokumenty

- [Moduly](modules.md) — mapa manifestů, katalogu, runtime registry, lifecycle, feature a public routing infrastruktury.
- [Admin assety](admin-assets.md) — package-owned Admin dist, host publishing a oddeleny Frontend build.
- [Admin request flow](admin-request-flow.md) — HTML stránky, JSON DataGrid a module actions.
- [AdminEditor](admin-editor.md) — deklarace editoru, renderování, modaly, validace a locky.
- [DataGrid](datagrid.md) — definice tabulky, filtry a řádkové akce.
- [Řádkové akce DataGridu](datagrid-row-actions.md) — typed kontrakt navigace, modalu, mutace, potvrzení a obnovení tabulky.
- [Oprávnění](permissions.md) — runtime katalog, role, výjimky a kontrola přístupu.
- [Identity administrace](identity.md) — principal, canonical actor identity a local-only capability.
- [Migrace](migrations.md) — globální a modulové migrace.
- [Oznámení jako příklad](notifications.md) — propojení modalu, service, modelu a recipient stavu.

Builtin System moduly jsou Audit, Languages, Modules, Notifications, Roles a Users. Jejich manifesty deklaruje package `composer.json` pod `extra.lemonade.modules`.
