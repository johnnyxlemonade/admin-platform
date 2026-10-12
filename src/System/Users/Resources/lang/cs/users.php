<?php

declare(strict_types=1);

$catalog = [
    'module' => ['name' => 'Uživatelé', 'description' => 'Správa uživatelských účtů a přístupů do administrace.'],
    'list' => [
        'title' => 'Uživatelé',
        'description' => 'Spravujte uživatelské účty, role a přístupy do administrace.',
        'search' => 'Hledat podle e-mailu...',
        'all' => 'Všichni',
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
        'deleted' => 'Smazaní',
        'empty' => 'Žádní uživatelé nebyli nalezeni.',
        'loading' => 'Načítání uživatelů…',
        'never_logged_in' => 'Zatím se nepřihlásil',
        'no_role' => 'Bez přiřazené role',
    ],
    'filters' => ['role' => 'Role', 'all_roles' => 'Všechny role'],
    'fields' => ['user' => 'Uživatel', 'first_name' => 'Jméno', 'last_name' => 'Příjmení', 'email' => 'E-mail', 'phone' => 'Telefon', 'role' => 'Role', 'status' => 'Stav', 'mfa' => 'Dvoufázové ověření', 'last_login' => 'Poslední přihlášení', 'password' => 'Nové heslo', 'avatar' => 'Avatar'],
    'status' => ['active' => 'Aktivní', 'inactive' => 'Neaktivní', 'deleted' => 'Smazaný', 'mfa_active' => 'Aktivní', 'mfa_inactive' => 'Nenastaveno'],
    'widgets' => ['active' => ['title' => 'Aktivní uživatelé', 'description' => 'Aktuální počet aktivních účtů v administraci.', 'metric' => 'aktivních uživatelů']],
    'permissions' => ['view' => 'Zobrazit uživatele', 'create' => 'Vytvářet uživatele', 'edit' => 'Editovat uživatele', 'disable' => 'Deaktivovat uživatele', 'delete' => 'Odstranit uživatele', 'restore' => 'Obnovit uživatele', 'manage_roles' => 'Spravovat role uživatelů', 'manage_permissions' => 'Spravovat oprávnění uživatelů', 'mfa_reset' => 'Resetovat dvoufázové ověření'],
    'actions' => ['create' => 'Přidat uživatele', 'edit' => 'Upravit', 'activate' => 'Aktivovat', 'deactivate' => 'Deaktivovat', 'delete' => 'Odstranit', 'restore' => 'Obnovit', 'mfa_reset' => 'Resetovat dvoufázové ověření', 'mfa_reset_success' => 'Dvoufázové ověření bylo resetováno.', 'mfa_not_configured' => 'Dvoufázové ověření není nastaveno.', 'activated' => 'Uživatel byl aktivován.', 'deactivated' => 'Uživatel byl deaktivován.', 'deleted' => 'Uživatel byl odstraněn.', 'restored' => 'Uživatel byl obnoven.'],
    'editor' => [
        'title' => 'Upravit uživatele', 'description' => 'Spravujte účet, roli a přístupy uživatele.', 'create_title' => 'Přidat uživatele', 'create_description' => 'Vytvořte nový uživatelský účet a přiřaďte mu roli.',
        'active' => 'Aktivní účet', 'active_help' => 'Neaktivní uživatel se nemůže přihlásit.', 'version' => 'Verze záznamu', 'external_profile_synced' => 'Profilové údaje jsou synchronizované z externí identity.',
        'roles_help' => 'Vyberte právě jednu roli. Role určuje efektivní oprávnění uživatele.', 'roles_read_only' => 'Nemáte oprávnění přiřazenou roli změnit.', 'role_placeholder' => 'Vyberte roli',
        'password_help' => 'Heslo se změní až po výslovném otevření této části.', 'create_password_help' => 'Nový lokální účet potřebuje heslo.', 'change_password' => 'Změnit heslo', 'password_show' => 'Zobrazit heslo', 'password_hide' => 'Skrýt heslo', 'avatar_upload' => 'Nahrát nebo změnit avatar', 'avatar_remove' => 'Odstranit avatar', 'avatar_help' => 'JPEG, PNG nebo WebP. Nahraný obrázek se ověří a uloží jako avatar uživatele.',
        'permissions_help' => 'Oprávnění dědí role. Změnou přepínače vytvoříte výjimku pro tohoto uživatele.', 'higher_administration_required' => 'Některá oprávnění tohoto uživatele může spravovat pouze vyšší úroveň administrace.', 'no_permissions' => 'Z vybrané role nevyplývá žádné oprávnění.', 'no_identities' => 'Uživatel zatím nemá připojenou externí identitu.', 'saved' => 'Uživatel byl uložen.', 'created' => 'Uživatel byl vytvořen.', 'profile_locked_by' => 'Uživatel {name} si právě upravuje svůj profil.', 'two_factor' => 'Dvoufázové ověření', 'mfa_manage' => 'Spravovat dvoufázové ověření', 'mfa_reset' => 'Resetovat dvoufázové ověření', 'mfa_manage_help' => 'Dvoufázové ověření spravujete v tomto okně.', 'mfa_active' => 'Aktivní', 'mfa_inactive' => 'Nenastaveno', 'summary' => ['created_at' => 'Vytvořeno', 'last_login' => 'Poslední přihlášení', 'mfa' => 'Dvoufázové ověření'], 'permission_inherited_allow' => 'Povoleno rolí', 'permission_inherited_deny' => 'Nepovoleno rolí', 'permission_allow' => 'Výslovně povoleno pro uživatele', 'permission_deny' => 'Výslovně zakázáno pro uživatele', 'permission_source_role' => 'Role', 'permission_source_override' => 'Výjimka', 'permission_count_label' => 'Vybraná oprávnění', 'permission_select_all' => 'Všechna oprávnění',
        'tabs' => ['basic' => 'Základní údaje', 'permissions' => 'Oprávnění', 'security' => 'Zabezpečení'],
        'sections' => ['account' => 'Účet', 'profile' => 'Profil', 'access' => 'Přístup a role', 'security' => 'Heslo', 'identities' => 'Připojené identity', 'permissions' => 'Oprávnění'],
    ],
    'confirm' => ['activate' => 'Opravdu chcete uživatele aktivovat?', 'deactivate' => 'Opravdu chcete uživatele deaktivovat?', 'delete' => 'Opravdu chcete uživatele odstranit?', 'restore' => 'Opravdu chcete uživatele obnovit?', 'mfa_reset' => 'Opravdu chcete resetovat dvoufázové ověření tohoto uživatele?'],
    'notifications' => [
        'created' => '{actor} vytvořil uživatele {user}.',
        'role_changed' => '{actor} změnil roli uživatele {user}.',
        'permissions_changed' => '{actor} změnil oprávnění uživatele {user}.',
        'activated' => '{actor} aktivoval uživatele {user}.',
        'deactivated' => '{actor} deaktivoval uživatele {user}.',
        'deleted' => '{actor} odstranil uživatele {user}.',
        'restored' => '{actor} obnovil uživatele {user}.',
    ],
    'audit' => [
        'events' => [
            'created' => 'Vytvořil uživatele {user}.',
            'updated' => 'Upravil profil uživatele {user}.',
            'password_changed' => 'Změnil heslo uživatele {user}.',
            'role_changed' => 'Změnil roli uživatele {user}.',
            'permissions_changed' => 'Změnil oprávnění uživatele {user}.',
            'activated' => 'Aktivoval uživatele {user}.',
            'deactivated' => 'Deaktivoval uživatele {user}.',
            'deleted' => 'Odstranil uživatele {user}.',
            'restored' => 'Obnovil uživatele {user}.',
        ],
    ],
    'validation' => ['first_name_required' => 'Zadejte jméno.', 'first_name_max_length' => 'Jméno může mít nejvýše 100 znaků.', 'last_name_required' => 'Zadejte příjmení.', 'last_name_max_length' => 'Příjmení může mít nejvýše 100 znaků.', 'phone_max_length' => 'Telefon může mít nejvýše 35 znaků.', 'email_required' => 'Zadejte e-mail.', 'email_invalid' => 'Zadejte platný e-mail.', 'email_max_length' => 'E-mail může mít nejvýše 254 znaků.', 'email_taken' => 'Tento e-mail už používá jiný uživatel.', 'status_required' => 'Zvolte stav účtu.', 'status_invalid' => 'Stav účtu není platný.', 'status_not_manageable' => 'Nemáte oprávnění změnit stav účtu.', 'version_required' => 'Chybí verze záznamu.', 'version_invalid' => 'Verze záznamu není platná.', 'role_required' => 'Vyberte roli.', 'role_invalid' => 'Vybraná role není platná.', 'role_not_assignable' => 'Vybranou roli nemůžete přiřadit.', 'permission_invalid' => 'Vybrané oprávnění není platné.', 'permissions_not_manageable' => 'Nemáte oprávnění spravovat oprávnění uživatele.', 'permission_not_delegable' => 'Nemůžete udělit oprávnění, které sami nemáte.', 'superadmin_overrides_forbidden' => 'Oprávnění superadministrátora nelze měnit výjimkami.', 'cannot_change_own_authorization' => 'Nemůžete měnit vlastní roli ani oprávnění.', 'password_required' => 'Zadejte heslo.', 'password_min_length' => 'Heslo musí mít alespoň 12 znaků.', 'cannot_deactivate_self' => 'Nemůžete deaktivovat vlastní účet.', 'cannot_delete_self' => 'Nemůžete odstranit vlastní účet.', 'cannot_delete_superadmin' => 'Superadministrátora může odstranit pouze jiný superadministrátor.', 'last_administrator' => 'Poslední aktivní administrátor nesmí ztratit administrátorský přístup.', 'user_not_deleted' => 'Uživatel není smazaný.'],
];

$catalog['validation']['external_profile_readonly'] = 'Profil uživatele s externím přihlášením nelze upravovat';
$catalog['validation']['external_password_forbidden'] = 'Uživateli s externím přihlášením nelze nastavit místní heslo';
$catalog['validation']['mfa_target_not_allowed'] = 'Tomuto uživateli nemůžete resetovat dvoufázové ověření';

return $catalog;
