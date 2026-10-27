<?php

declare(strict_types=1);

return [
    'module' => [
        'name' => 'Modules',
        'description' => 'Overview of modules available in the Composer installation.',
    ],

    'widgets' => [
        'active' => [
            'title' => 'Available modules',
            'description' => 'Overview of modules available in the administration.',
            'empty' => 'No modules are available for your account.',
        ],
    ],

    'list' => [
        'title' => 'Modules',
        'description' => 'Modules available in the current Composer installation.',
        'all' => 'All',
        'search' => 'Search modules',
        'loading' => 'Loading modules…',
        'empty' => 'No modules found.',
    ],

    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'version' => 'Version',
        'description' => 'Description',
    ],

    'permissions' => [
        'view' => 'View modules',
    ],
];
