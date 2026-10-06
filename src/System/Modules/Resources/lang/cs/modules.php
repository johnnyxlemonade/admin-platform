<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Moduly'],
    'widgets' => ['active' => ['title' => 'Aktivní moduly', 'description' => 'Přehled modulů dostupných v administraci.', 'empty' => 'Pro váš účet nejsou dostupné žádné aktivní moduly.']],
    'audit' => ['installed' => 'Modul byl nainstalován.', 'enabled' => 'Modul byl zapnut.', 'disabled' => 'Modul byl vypnut.', 'route_prefix_synchronized' => 'Výchozí veřejný prefix modulu byl doplněn.', 'feature_enabled' => 'Funkce modulu byla zapnuta.', 'feature_disabled' => 'Funkce modulu byla vypnuta.', 'feature_synchronized' => 'Funkce modulu byla synchronizována.'],
    'list' => ['title' => 'Moduly', 'description' => 'Správa nainstalovaných modulů administrace.', 'all' => 'Všechny', 'system' => 'Systémové', 'optional' => 'Volitelné', 'search' => 'Hledat moduly', 'loading' => 'Načítání modulů…', 'empty' => 'Žádné moduly nenalezeny.'],
    'fields' => ['name' => 'Název', 'code' => 'Kód', 'kind' => 'Druh', 'state' => 'Stav'],
    'kind' => ['system' => 'Systémový', 'optional' => 'Volitelný'],
    'state' => ['system' => 'Systémový', 'available' => 'Dostupný', 'disabled' => 'Nainstalovaný, vypnutý', 'enabled' => 'Nainstalovaný, zapnutý', 'missing_code' => 'Chybějící kód'],
    'permissions' => ['view' => 'Zobrazit moduly', 'install' => 'Instalovat moduly', 'enable' => 'Zapínat moduly', 'disable' => 'Vypínat moduly', 'manage_features' => 'Spravovat funkce modulů'],
    'actions' => ['install' => 'Instalovat', 'enable' => 'Zapnout', 'disable' => 'Vypnout', 'features' => 'Funkce'],
    'features' => [
        'title' => 'Funkce modulu',
        'description' => 'Přehled schopností modulu a jejich runtime stavu.',
        'breadcrumb' => 'Funkce modulu',
        'listTitle' => 'Deklarované funkce',
        'empty' => 'Modul nemá deklarované funkce.',
        'required' => 'Povinná',
        'fields' => ['feature' => 'Funkce', 'category' => 'Kategorie', 'configured' => 'Nakonfigurováno', 'effective' => 'Efektivní stav'],
        'categories' => ['admin_capability' => 'Administrační capability', 'structural_capability' => 'Strukturální capability', 'toggle' => 'Volitelná funkce'],
        'states' => ['enabled' => 'Zapnuto', 'disabled' => 'Vypnuto'],
        'readOnly' => ['system' => 'Systémová funkce', 'notInstalled' => 'Nejprve nainstalujte modul', 'required' => 'Povinná funkce', 'capability' => 'Pouze informativní', 'permission' => 'Chybí oprávnění'],
        'notices' => ['notInstalled' => 'Modul není nainstalovaný. Zobrazená deklarace je pouze informativní.', 'disabled' => 'Modul je vypnutý. Nakonfigurovaný stav lze změnit, efektivní stav zůstává vypnutý.'],
        'actions' => ['toggle' => 'Změnit stav', 'enable' => 'Zapnout funkci', 'disable' => 'Vypnout funkci', 'enabled' => 'Funkce byla zapnuta.', 'disabled' => 'Funkce byla vypnuta.'],
    ],
    'confirm' => ['install' => 'Instalovat tento modul?', 'enable' => 'Zapnout tento modul?', 'disable' => 'Vypnout tento modul?'],
    'errors' => ['invalid_module' => 'Neplatný modul.', 'invalid_state' => 'Modul nelze v aktuálním stavu změnit.', 'install_failed' => 'Instalaci modulu se nepodařilo dokončit.', 'invalid_feature' => 'Funkci nelze v aktuálním stavu změnit.'],
];
