<?php

declare(strict_types=1);

return [
    'module' => ['name' => 'Modules', 'description' => 'Manage available and installed modules.'],
    'widgets' => ['active' => ['title' => 'Active modules', 'description' => 'Overview of modules available in the administration.', 'empty' => 'No active modules are available for your account.']],
    'audit' => ['installed' => 'Installed module.', 'enabled' => 'Enabled module.', 'disabled' => 'Disabled module.'],
    'list' => ['title' => 'Modules', 'description' => 'Manage installed administration modules.', 'all' => 'All', 'system' => 'System', 'optional' => 'Optional', 'search' => 'Search modules', 'loading' => 'Loading modules…', 'empty' => 'No modules found.'],
    'fields' => ['name' => 'Name', 'code' => 'Code', 'kind' => 'Kind', 'state' => 'State'],
    'kind' => ['system' => 'System', 'optional' => 'Optional'],
    'state' => ['system' => 'System', 'available' => 'Available', 'disabled' => 'Installed, disabled', 'enabled' => 'Installed, enabled', 'missing_code' => 'Missing code'],
    'permissions' => ['view' => 'View modules', 'install' => 'Install modules', 'enable' => 'Enable modules', 'disable' => 'Disable modules'],
    'actions' => ['install' => 'Install', 'enable' => 'Enable', 'disable' => 'Disable'],
    'confirm' => ['install' => 'Install this module?', 'enable' => 'Enable this module?', 'disable' => 'Disable this module?'],
    'errors' => ['invalid_module' => 'Invalid module.', 'invalid_state' => 'Module cannot be changed in its current state.', 'install_failed' => 'The module installation could not be completed.'],
];
