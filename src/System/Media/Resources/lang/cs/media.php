<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Média'],
    'permissions' => ['view' => 'Zobrazit média'],
    'list' => ['title' => 'Média', 'description' => 'Přehled souborů uložených v systému.', 'search' => 'Hledat v médiích…', 'loading' => 'Načítání médií…', 'empty' => 'Nejsou zde žádné soubory.', 'all' => 'Vše', 'image' => 'Obrázky', 'document' => 'Dokumenty', 'video' => 'Videa', 'other' => 'Ostatní'],
    'fields' => ['file' => 'Soubor', 'type' => 'Typ', 'size_dimensions' => 'Velikost / rozměry', 'owner_usage' => 'Vlastník / použití', 'created_at' => 'Nahráno'],
    'filters' => ['module' => 'Modul', 'all_modules' => 'Všechny moduly'],
];
