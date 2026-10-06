<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Překlady'],
    'audit' => [
        'events' => [
            'created' => 'Vlastní překlad vytvořen',
            'updated' => 'Vlastní překlad upraven',
            'removed' => 'Vlastní překlad odstraněn',
        ],
    ],
    'list' => [
        'title' => 'Překlady',
        'description' => 'Správa překladů aplikace a modulů',
        'all' => 'Všechny',
        'loading' => 'Načítání překladů…',
        'empty' => 'Pro vybrané filtry nebyly nalezeny žádné překlady',
    ],
    'filters' => [
        'locale' => 'Jazyk',
        'all_locales' => 'Všechny jazyky',
        'owner' => 'Oblast',
        'all_owners' => 'Všechny oblasti',
        'status' => 'Stav',
        'all_statuses' => 'Všechny stavy',
    ],
    'fields' => [
        'locale' => 'Jazyk',
        'owner' => 'Oblast',
        'group' => 'Skupina',
        'key' => 'Klíč',
        'source' => 'Zdrojový překlad',
        'effective' => 'Výsledný překlad',
        'override' => 'Vlastní překlad',
        'status' => 'Stav',
    ],
    'status' => [
        'default' => 'Výchozí',
        'overridden' => 'Upraveno',
        'missing' => 'Chybí překlad',
    ],
    'permissions' => [
        'view' => 'Zobrazit překlady',
        'edit' => 'Upravovat překlady',
    ],
    'actions' => [
        'reset' => 'Obnovit výchozí překlad',
        'bulk_reset' => 'Obnovit vybrané překlady',
    ],
    'confirm' => [
        'reset' => 'Odstranit vlastní překlad a vrátit výchozí hodnotu?',
        'bulk_reset' => 'Opravdu chcete u vybraných překladů odstranit vlastní hodnoty a vrátit výchozí překlady?',
    ],
    'editor' => [
        'title' => 'Upravit překlad',
        'description' => 'Vlastní hodnota překladu nahradí výchozí překlad',
        'identity' => 'Identita překladu',
        'values' => 'Hodnoty',
        'saved' => 'Překlad byl uložen',
    ],
    'validation' => [
        'create_not_supported' => 'Nové překlady nelze vytvářet v administraci',
        'bulk_ids_invalid' => 'Vyberte alespoň jeden platný překlad',
        'source_key_not_found' => 'Vybraný výchozí překlad nebyl nalezen',
        'override_not_saved' => 'Vlastní překlad se nepodařilo uložit',
        'override_not_removed' => 'Vlastní překlad se nepodařilo odstranit',
        'unsupported_locale' => 'Vybraný jazyk není podporovaný',
        'invalid_identity' => 'Identita překladu není platná',
    ],
];
