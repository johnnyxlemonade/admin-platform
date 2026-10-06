<?php

declare(strict_types=1);

return [
    'login' => [
        'page_title' => 'Přihlášení',
        'title' => 'Přihlášení',
        'description' => [
            'local_only' => 'Přihlaste se pomocí lokálního účtu.',
            'local_or_provider' => 'Pokračujte přes {provider} nebo se přihlaste lokálním účtem.',
            'local_or_sso' => 'Pokračujte přes jednotné přihlášení nebo se přihlaste lokálním účtem.',
        ],
        'email' => 'E-mail', 'password' => 'Heslo', 'remember' => 'Zůstat přihlášen', 'forgot' => 'Zapomenuté heslo?', 'submit' => 'Přihlásit se', 'or' => 'nebo', 'sso' => 'Přihlásit přes jednotné přihlášení', 'sso_provider' => 'Přihlásit přes {provider}', 'back' => 'Zpět na web',
    ],
    'password' => ['show' => 'Zobrazit heslo', 'hide' => 'Skrýt heslo'],
    'forgot' => ['title' => 'Zapomenuté heslo', 'description' => 'Zadejte e-mail, který používáte pro přihlášení. Zašleme vám odkaz pro nastavení nového hesla.', 'submit' => 'Odeslat odkaz', 'back' => 'Zpět na přihlášení', 'errors' => ['email' => 'Zadejte platnou e-mailovou adresu.'], 'unavailable' => 'Funkce obnovení hesla momentálně není dostupná.'],
    'errors' => ['required' => 'Vyplňte e-mail a heslo.', 'invalid' => 'Přihlašovací údaje nejsou platné.', 'disabled' => 'Tento účet je deaktivovaný.', 'csrf' => 'Bezpečnostní token vypršel. Zkuste to prosím znovu.'],
    'audit' => [
        'module' => ['name' => 'Autentizace'],
        'events' => ['login' => 'Přihlášení', 'logout' => 'Odhlášení'],
    ],
    'locale' => ['label' => 'Jazyk rozhraní', 'czech' => 'Čeština', 'english' => 'English'],
    'brand' => ['subtitle' => 'Redakční a administrační systém', 'poweredByLemonade' => 'Vytvořeno na Lemonade Framework'],
    'theme' => ['appearance' => 'Vzhled', 'system' => 'Systém', 'light' => 'Světlý', 'dark' => 'Tmavý'],
    'common' => ['close' => 'Zavřít'],
];
