<?php

declare(strict_types=1);

return [
    'module' => [
        'name' => 'Moduly',
        'description' => 'Přehled modulů dostupných v Composer instalaci.',
    ],

    'widgets' => [
        'active' => [
            'title' => 'Dostupné moduly',
            'description' => 'Přehled modulů dostupných v administraci.',
            'empty' => 'Pro váš účet nejsou dostupné žádné moduly.',
        ],
    ],

    'list' => [
        'title' => 'Moduly',
        'description' => 'Moduly dostupné v aktuální Composer instalaci.',
        'all' => 'Všechny',
        'search' => 'Hledat moduly',
        'loading' => 'Načítání modulů…',
        'empty' => 'Žádné moduly nenalezeny.',
    ],

    'fields' => [
        'name' => 'Název',
        'code' => 'Kód',
        'version' => 'Verze',
        'description' => 'Popis',
    ],

    'permissions' => [
        'view' => 'Zobrazit moduly',
    ],
];
