<?php

declare(strict_types=1);

return [
    'module' => [
        'name' => 'Audit',
        'description' => 'Přehled důležitých změn provedených v administraci.',
    ],
    'list' => [
        'title' => 'Auditní log',
        'description' => 'Prohlížejte historii důležitých změn v administraci.',
        'empty' => 'Zatím nebyla zaznamenána žádná auditní událost.',
        'loading' => 'Načítání auditního logu…',
    ],
    'fields' => [
        'created_at' => 'Čas',
        'actor' => 'Kdo',
        'action' => 'Akce',
        'module' => 'Modul',
        'entity' => 'Entita',
    ],
    'filters' => [
        'module' => 'Modul',
        'all_modules' => 'Všechny moduly',
    ],
    'actor' => [
        'system' => 'Systém',
        'external' => 'Externí identita',
        'unknown' => 'Neznámý uživatel #{id}',
    ],
    'permissions' => [
        'view' => 'Zobrazit auditní log',
    ],
    'widgets' => [
        'recent' => ['title' => 'Poslední události auditu', 'description' => 'Nejnovější změny zaznamenané v administraci.', 'empty' => 'Zatím nejsou zaznamenané žádné aktivity.', 'view_all' => 'Zobrazit Auditní log'],
        'my_logins' => ['title' => 'Moje přihlášení', 'empty' => 'Zatím žádná přihlášení', 'methods' => ['local' => 'Lokální', 'oidc' => 'OIDC', 'unknown' => 'Neznámá']],
    ],
];
