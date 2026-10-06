# Oprávnění

## Katalog a vyhodnocení

Každý systémový `*ModuleDefinition` vrací `PermissionDefinition` a provider ji zaregistruje v `PermissionCatalogRegistry`. Katalog je runtime kontrakt. `system_permission` ukládá kód, vlastnící modul a lokalizační klíč, ale závislosti `requires` jsou v definici. `PermissionDependencyResolver` doplní předpoklady při ukládání role.

`AuthorizationService` sestavuje efektivní oprávnění z role (`system_role_permission`) a uživatelských výjimek (`system_user_permission`). Uživatel s rolí superadministrátora dostává kompletní katalog. `system_user_role` drží přiřazení role; individuální override má effect allow nebo deny.

## Role versus přiřazení role

`system.roles.*` spravuje katalog rolí a jejich permission set v `RoleService`. `system.users.manage_roles` dovoluje pouze přiřadit existující roli uživateli v editoru Uživatelů; není to oprávnění ke správě katalogu rolí. `system.users.manage_permissions` spravuje individuální výjimky uživatele.

`RoleService` pri delete a restore odmitne systemovou i superadmin roli; nesuperadministrator nemuze upravit superadmin roli. Omezuje delegovaneho spravce na delegovatelna opravneni a brani nesuperadministratorovi zmenit permission set vlastni prirazene role tak, aby ztratil pravo upravovat role. `UserService` chrani bezpecnostni invarianty uzivatele, vcetne akci, ktere by odstranily posledniho superadministratora.

## Kde se kontroluje přístup

`AdminNavigation` používá navigation permission pouze pro viditelnost sidebaru. `ModuleController`, `DataGridController` a `EditorDispatcher` kontrolují capability a permission pro request. `ModuleActionDispatcher` kontroluje permission z `ModuleActionDefinition`; service vrstva kontroluje business pravidla a podle potřeby i oprávnění cílové entity. Viditelná DataGrid action nikdy není autorizace.

Například `system.languages.create` vyžaduje `system.languages.view`; `system.modules.*` a `system.audit.view` mají delegaci pouze pro superadministrátora. Route/action chyby v JSON transportu vracejí 403 structured payload, zatímco HTML request použije administrační response flow.
