<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Translations'],
    'audit' => [
        'events' => [
            'created' => 'Custom translation created',
            'updated' => 'Custom translation updated',
            'removed' => 'Custom translation removed',
        ],
    ],
    'list' => [
        'title' => 'Translations',
        'description' => 'Manage application and module translations',
        'all' => 'All',
        'loading' => 'Loading translations…',
        'empty' => 'No translations match the selected filters',
    ],
    'filters' => [
        'locale' => 'Locale',
        'all_locales' => 'All locales',
        'owner' => 'Domain',
        'all_owners' => 'All domains',
        'status' => 'Status',
        'all_statuses' => 'All statuses',
    ],
    'fields' => [
        'locale' => 'Locale',
        'owner' => 'Domain',
        'group' => 'Group',
        'key' => 'Key',
        'source' => 'Source translation',
        'effective' => 'Resulting translation',
        'override' => 'Custom translation',
        'status' => 'Status',
    ],
    'status' => [
        'default' => 'Default',
        'overridden' => 'Modified',
        'missing' => 'Missing translation',
    ],
    'permissions' => [
        'view' => 'View translations',
        'edit' => 'Edit translations',
    ],
    'actions' => [
        'reset' => 'Restore default translation',
        'bulk_reset' => 'Restore selected translations',
    ],
    'confirm' => [
        'reset' => 'Remove the custom translation and restore the default value?',
        'bulk_reset' => 'Remove custom values from the selected translations and restore their default translations?',
    ],
    'editor' => [
        'title' => 'Edit translation',
        'description' => 'A custom translation replaces the default translation',
        'identity' => 'Translation identity',
        'values' => 'Values',
        'saved' => 'Translation saved',
    ],
    'validation' => [
        'create_not_supported' => 'New translations cannot be created in administration',
        'bulk_ids_invalid' => 'Select at least one valid translation',
        'source_key_not_found' => 'The selected default translation was not found',
        'override_not_saved' => 'The custom translation could not be saved',
        'override_not_removed' => 'The custom translation could not be removed',
        'unsupported_locale' => 'The selected locale is not supported',
        'invalid_identity' => 'The translation identity is invalid',
    ],
];
