<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Moduly', 'description' => 'Správa dostupných a nainstalovaných modulů.'],
    'widgets' => ['active' => ['title' => 'Aktivní moduly', 'description' => 'Přehled modulů dostupných v administraci.', 'empty' => 'Pro váš účet nejsou dostupné žádné aktivní moduly.']],
    'audit' => ['installed' => 'Modul byl nainstalován.', 'enabled' => 'Modul byl zapnut.', 'disabled' => 'Modul byl vypnut.'],
    'list' => ['title' => 'Moduly', 'description' => 'Správa nainstalovaných modulů administrace.', 'all' => 'Všechny', 'system' => 'Systémové', 'optional' => 'Volitelné', 'search' => 'Hledat moduly', 'loading' => 'Načítání modulů…', 'empty' => 'Žádné moduly nenalezeny.'],
    'fields' => ['name' => 'Název', 'code' => 'Kód', 'kind' => 'Druh', 'state' => 'Stav'],
    'kind' => ['system' => 'Systémový', 'optional' => 'Volitelný'],
    'state' => ['system' => 'Systémový', 'available' => 'Dostupný', 'disabled' => 'Nainstalovaný, vypnutý', 'enabled' => 'Nainstalovaný, zapnutý', 'missing_code' => 'Chybějící kód'],
    'permissions' => ['view' => 'Zobrazit moduly', 'install' => 'Instalovat moduly', 'enable' => 'Zapínat moduly', 'disable' => 'Vypínat moduly'],
    'actions' => ['install' => 'Instalovat', 'enable' => 'Zapnout', 'disable' => 'Vypnout'],
    'confirm' => ['install' => 'Instalovat tento modul?', 'enable' => 'Zapnout tento modul?', 'disable' => 'Vypnout tento modul?'],
    'errors' => ['invalid_module' => 'Neplatný modul.', 'invalid_state' => 'Modul nelze v aktuálním stavu změnit.', 'install_failed' => 'Instalaci modulu se nepodařilo dokončit.'],
];
