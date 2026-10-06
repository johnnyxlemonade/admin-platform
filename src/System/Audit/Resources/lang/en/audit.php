<?php

declare(strict_types=1);

return [
    'module' => [
        'name' => 'Audit',
        'description' => 'Overview of important changes made in the administration.',
    ],
    'list' => [
        'title' => 'Audit Log',
        'description' => 'Review the history of important administration changes.',
        'empty' => 'No audit events have been recorded yet.',
        'loading' => 'Loading audit log…',
    ],
    'fields' => [
        'created_at' => 'Time',
        'actor' => 'Who',
        'action' => 'Action',
        'module' => 'Module',
        'entity' => 'Entity',
    ],
    'filters' => [
        'module' => 'Module',
        'all_modules' => 'All modules',
    ],
    'actor' => [
        'system' => 'System',
        'external' => 'External identity',
        'unknown' => 'Unknown user #{id}',
    ],
    'permissions' => [
        'view' => 'View audit log',
    ],
    'widgets' => [
        'recent' => ['title' => 'Recent audit events', 'description' => 'Most recent changes recorded in administration.', 'empty' => 'No activity has been recorded yet.', 'view_all' => 'View audit log'],
        'my_logins' => ['title' => 'My logins', 'empty' => 'No logins yet', 'methods' => ['local' => 'Local', 'oidc' => 'OIDC', 'unknown' => 'Unknown']],
    ],
];
