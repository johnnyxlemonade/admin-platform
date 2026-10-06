<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Jazyky'],
    'permissions' => ['view' => 'Zobrazit jazyky', 'create' => 'Vytvářet jazyky', 'edit' => 'Upravovat jazyky', 'enable' => 'Aktivovat jazyky', 'disable' => 'Deaktivovat jazyky', 'set_default' => 'Nastavit výchozí jazyk'],
    'list' => ['title' => 'Jazyky', 'description' => 'Správa jazyků dostupných pro systémový obsah.', 'all' => 'Všechny', 'enabled' => 'Aktivní', 'disabled' => 'Neaktivní', 'default' => 'Výchozí', 'not_default' => '—', 'search' => 'Hledat jazyky', 'loading' => 'Načítání jazyků…', 'empty' => 'Žádné jazyky nenalezeny.'],
    'fields' => ['preset' => 'Jazykový preset', 'code' => 'Kód', 'name' => 'Název', 'flag_code' => 'Vlajka', 'enabled' => 'Stav', 'default' => 'Výchozí', 'sort_order' => 'Pořadí'],
    'filters' => ['status' => 'Stav', 'all_statuses' => 'Všechny stavy'],
    'actions' => ['create' => 'Přidat jazyk', 'enable' => 'Aktivovat', 'disable' => 'Deaktivovat', 'set_default' => 'Nastavit jako výchozí', 'edit_language' => 'Upravit jazyk {name}', 'enable_language' => 'Aktivovat jazyk {name}', 'disable_language' => 'Deaktivovat jazyk {name}', 'set_default_language' => 'Nastavit jazyk {name} jako výchozí', 'enabled' => 'Jazyk aktivován', 'disabled' => 'Jazyk deaktivován', 'default_changed' => 'Výchozí jazyk změněn'],
    'confirm' => ['enable_title' => 'Aktivovat jazyk?', 'enable' => 'Opravdu chcete tento jazyk aktivovat?', 'disable_title' => 'Deaktivovat jazyk?', 'disable' => 'Opravdu chcete tento jazyk deaktivovat?', 'set_default' => 'Opravdu chcete nastavit tento jazyk jako výchozí?'],
    'editor' => ['create_title' => 'Přidat jazyk', 'create_description' => 'Vyplňte základní údaje nového jazyka.', 'title' => 'Upravit jazyk', 'description' => 'Upravte název, vlajku a pořadí jazyka.', 'saved' => 'Jazyk byl aktualizován.', 'created' => 'Jazyk byl vytvořen.', 'preset_help' => 'Volba presetu předvyplní kód, nativní název a výchozí vlajku. Vlastní jazyk můžete zadat i nadále.', 'code_read_only' => 'Kód jazyka po vytvoření nelze změnit.', 'enabled_read_only' => 'Stav změňte akcí v seznamu jazyků.', 'tabs' => ['basic' => 'Základní údaje'], 'sections' => ['details' => 'Základní údaje']],
    'validation' => ['not_found' => 'Jazyk nebyl nalezen.', 'code_required' => 'Zadejte kód jazyka.', 'code_max_length' => 'Kód jazyka může mít nejvýše 35 znaků.', 'code_taken' => 'Tento kód jazyka již existuje.', 'name_required' => 'Zadejte název jazyka.', 'name_max_length' => 'Název jazyka může mít nejvýše 100 znaků.', 'flag_code_required' => 'Zadejte kód vlajky.', 'flag_code_invalid' => 'Kód vlajky musí mít dvě velká písmena ISO 3166-1.', 'sort_order_required' => 'Zadejte pořadí.', 'sort_order_invalid' => 'Pořadí musí být celé číslo.', 'default_cannot_be_disabled' => 'Výchozí jazyk nelze deaktivovat.', 'default_must_be_enabled' => 'Výchozí jazyk musí být aktivní.', 'default_invariant' => 'Systém musí mít právě jeden aktivní výchozí jazyk.'],
    'audit' => ['events' => ['created' => 'Jazyk vytvořen', 'updated' => 'Jazyk upraven', 'enabled' => 'Jazyk aktivován', 'disabled' => 'Jazyk deaktivován', 'default_changed' => 'Výchozí jazyk změněn']],
];
