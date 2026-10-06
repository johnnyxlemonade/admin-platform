<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Modules'],
    'widgets' => ['active' => ['title' => 'Active modules', 'description' => 'Overview of modules available in the administration.', 'empty' => 'No active modules are available for your account.']],
    'audit' => ['installed' => 'Installed module.', 'enabled' => 'Enabled module.', 'disabled' => 'Disabled module.', 'route_prefix_synchronized' => 'A default public module route prefix was synchronized.', 'feature_enabled' => 'Module feature enabled.', 'feature_disabled' => 'Module feature disabled.', 'feature_synchronized' => 'Module feature synchronized.'],
    'list' => ['title' => 'Modules', 'description' => 'Manage installed administration modules.', 'all' => 'All', 'system' => 'System', 'optional' => 'Optional', 'search' => 'Search modules', 'loading' => 'Loading modules…', 'empty' => 'No modules found.'],
    'fields' => ['name' => 'Name', 'code' => 'Code', 'kind' => 'Kind', 'state' => 'State'],
    'kind' => ['system' => 'System', 'optional' => 'Optional'],
    'state' => ['system' => 'System', 'available' => 'Available', 'disabled' => 'Installed, disabled', 'enabled' => 'Installed, enabled', 'missing_code' => 'Missing code'],
    'permissions' => ['view' => 'View modules', 'install' => 'Install modules', 'enable' => 'Enable modules', 'disable' => 'Disable modules', 'manage_features' => 'Manage module features'],
    'actions' => ['install' => 'Install', 'enable' => 'Enable', 'disable' => 'Disable', 'features' => 'Features'],
    'features' => [
        'title' => 'Module features',
        'description' => 'Overview of the module capabilities and their runtime state.',
        'breadcrumb' => 'Module features',
        'listTitle' => 'Declared features',
        'empty' => 'The module has no declared features.',
        'required' => 'Required',
        'fields' => ['feature' => 'Feature', 'category' => 'Category', 'configured' => 'Configured', 'effective' => 'Effective state'],
        'categories' => ['admin_capability' => 'Admin capability', 'structural_capability' => 'Structural capability', 'toggle' => 'Optional feature'],
        'states' => ['enabled' => 'Enabled', 'disabled' => 'Disabled'],
        'readOnly' => ['system' => 'System feature', 'notInstalled' => 'Install the module first', 'required' => 'Required feature', 'capability' => 'Informational only', 'permission' => 'Permission required'],
        'notices' => ['notInstalled' => 'The module is not installed. The declaration is informational only.', 'disabled' => 'The module is disabled. The configured state can change, but the effective state remains disabled.'],
        'actions' => ['toggle' => 'Change state', 'enable' => 'Enable feature', 'disable' => 'Disable feature', 'enabled' => 'Feature enabled.', 'disabled' => 'Feature disabled.'],
    ],
    'confirm' => ['install' => 'Install this module?', 'enable' => 'Enable this module?', 'disable' => 'Disable this module?'],
    'errors' => ['invalid_module' => 'Invalid module.', 'invalid_state' => 'Module cannot be changed in its current state.', 'install_failed' => 'The module installation could not be completed.', 'invalid_feature' => 'Feature cannot be changed in its current state.'],
];
