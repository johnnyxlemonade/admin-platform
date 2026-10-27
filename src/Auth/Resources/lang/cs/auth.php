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
        'email' => 'E-mail',
        'password' => 'Heslo',
        'remember' => 'Zůstat přihlášen',
        'forgot' => 'Zapomenuté heslo?',
        'submit' => 'Přihlásit se',
        'or' => 'nebo',
        'sso' => 'Přihlásit přes jednotné přihlášení',
        'sso_provider' => 'Přihlásit přes {provider}',
        'back' => 'Zpět na web',
    ],

    'password' => [
        'show' => 'Zobrazit heslo',
        'hide' => 'Skrýt heslo',
    ],

    'session_lock' => [
        'title' => 'Relace je uzamčená',
        'description' => 'Relace byla po neaktivitě uzamčena. Pro pokračování potvrďte heslo.',
        'oidc_description' => 'Pro pokračování potvrďte svou identitu u poskytovatele jednotného přihlášení.',
        'verify' => 'Pro pokračování v práci se prosím znovu ověřte.',
        'or' => 'nebo',
        'safe' => 'Vaše data zůstávají v bezpečí.',
        'password' => 'Heslo',
        'unlock' => 'Odemknout',
        'oidc' => 'Pokračovat přes jednotné přihlášení',
        'logout' => 'Odhlásit se',
        'invalid' => 'Heslo není platné.',
        'throttled' => 'Příliš mnoho pokusů. Chvíli počkejte a zkuste to znovu.',
        'popup_blocked' => 'Pro pokračování přes jednotné přihlášení povolte popup okna.',
        'oidc_failed' => 'Ověření přes jednotné přihlášení nebylo dokončeno. Zkuste to znovu.',
    ],

    'forgot' => [
        'title' => 'Zapomenuté heslo',
        'description' => 'Zadejte e-mail, který používáte pro přihlášení. Zašleme vám odkaz pro nastavení nového hesla.',
        'submit' => 'Odeslat odkaz',
        'back' => 'Zpět na přihlášení',
        'errors' => [
            'email' => 'Zadejte platnou e-mailovou adresu.',
        ],
        'unavailable' => 'Funkce obnovení hesla momentálně není dostupná.',
    ],

    'errors' => [
        'required' => 'Vyplňte e-mail a heslo.',
        'invalid' => 'Přihlašovací údaje nejsou platné.',
        'disabled' => 'Tento účet je deaktivovaný.',
        'session_expired' => 'Vaše relace vypršela. Přihlaste se prosím znovu.',
        'throttled_seconds' => 'Příliš mnoho pokusů. Zkuste to znovu za {seconds} sekund.',
        'throttled_minutes' => 'Příliš mnoho pokusů. Zkuste to znovu za {minutes} min.',
        'csrf' => 'Bezpečnostní token vypršel. Zkuste to prosím znovu.',
    ],

    'mfa' => [
        'title' => 'Dvoufázové ověření',
        'challenge_description' => 'Zadejte kód z aplikace pro ověřování.',
        'code' => 'Ověřovací kód',
        'code_placeholder' => 'Zadejte 6místný kód z aplikace',
        'verify' => 'Ověřit',
        'enroll_title' => 'Nastavení dvoufázového ověření',
        'enroll_description' => 'Zahajte nastavení a vytvořte nové ověření v aplikaci.',
        'enroll_start' => 'Zahájit nastavení',
        'enroll_scan' => 'Naskenujte QR kód v aplikaci pro ověřování a zadejte její kód.',
        'app_title' => 'Použijte aplikaci pro ověřování',
        'app_description' => 'Naskenujte QR kód v aplikaci Google Authenticator, Microsoft Authenticator nebo jiné kompatibilní aplikaci.',
        'manual_title' => 'Nemůžete naskenovat kód?',
        'manual_description' => 'Zadejte níže uvedený klíč pro ruční zadání do vaší aplikace pro ověřování.',
        'manual_key' => 'Klíč pro ruční zadání',
        'copy' => 'Kopírovat',
        'enroll_confirm' => 'Potvrdit nastavení',
        'profile_title' => 'Dvoufázové ověření',
        'active' => 'Dvoufázové ověření je aktivní.',
        'inactive' => 'Dvoufázové ověření není aktivní.',
        'reauthenticate' => 'Potvrdit heslo',
        'reenroll' => 'Nahradit ověřovací aplikaci',
        'disable' => 'Vypnout dvoufázové ověření',
        'enrolled' => 'Dvoufázové ověření bylo nastaveno.',
        'disabled' => 'Dvoufázové ověření bylo vypnuto.',
        'errors' => [
            'invalid' => 'Ověřovací kód není platný nebo již byl použit.',
            'throttled_seconds' => 'Příliš mnoho pokusů. Zkuste to znovu za {seconds} sekund.',
            'throttled_minutes' => 'Příliš mnoho pokusů. Zkuste to znovu za {minutes} min.',
            'reauthenticate_required' => 'Nejprve potvrďte heslo.',
            'disable_not_allowed' => 'Dvoufázové ověření nelze při této policy vypnout.',
        ],
        'reenroll_title' => 'Znovu nastavit dvoufázové ověření',
        'reauth_description' => 'Pro nastavení dvoufázového ověření je z bezpečnostních důvodů nutné znovu potvrdit vaše heslo.',
        'reauth_help' => 'Tímto potvrdíte, že jste to skutečně vy. Vaše heslo se nikam neukládá.',
        'active_description' => 'Váš účet je zabezpečen pomocí aplikace pro ověřovací kódy.',
        'success_title' => 'Dvoufázové ověření bylo úspěšně nastaveno',
        'success_description' => 'Od nynějška se budete při přihlášení ověřovat pomocí kódu z vaší autentizační aplikace.',
    ],

    'audit' => [
        'module' => [
            'name' => 'Autentizace',
        ],
        'events' => [
            'login' => 'Přihlášení',
            'logout' => 'Odhlášení',
            'mfa_enrolled' => 'Dvoufázové ověření nastaveno',
            'mfa_disabled' => 'Dvoufázové ověření vypnuto',
            'mfa_reset' => 'Dvoufázové ověření resetováno',
            'mfa_challenge_failed' => 'Dvoufázové ověření selhalo',
            'login_failed' => 'Neúspěšné přihlášení',
            'login_throttled' => 'Přihlášení omezeno limitem',
            'mfa_failed' => 'Neúspěšné MFA ověření',
            'mfa_throttled' => 'MFA ověření omezeno limitem',
            'session_unlock_failed' => 'Neúspěšné odemknutí relace',
            'session_unlock_throttled' => 'Odemknutí relace omezeno limitem',
        ],
    ],

    'locale' => [
        'label' => 'Jazyk rozhraní',
        'czech' => 'Čeština',
        'english' => 'English',
    ],

    'brand' => [
        'subtitle' => 'Redakční a administrační systém',
        'poweredByLemonade' => 'Vytvořeno na Lemonade Framework',
    ],

    'theme' => [
        'appearance' => 'Vzhled',
        'system' => 'Systém',
        'light' => 'Světlý',
        'dark' => 'Tmavý',
    ],

    'common' => [
        'close' => 'Zavřít',
    ],
];
