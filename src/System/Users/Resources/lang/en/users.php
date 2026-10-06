<?php

declare(strict_types=1);

$catalog = [
    'module' => ['name' => 'Users', 'description' => 'Manage user accounts and administration access.'],
    'list' => [
        'title' => 'Users',
        'description' => 'Manage user accounts, roles and access to the administration.',
        'search' => 'Search by email...',
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'deleted' => 'Deleted',
        'empty' => 'No users found.',
        'loading' => 'Loading users…',
        'never_logged_in' => 'Never signed in',
        'no_role' => 'No assigned role',
    ],
    'filters' => ['role' => 'Role', 'all_roles' => 'All roles'],
    'fields' => ['user' => 'User', 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'phone' => 'Phone', 'role' => 'Role', 'status' => 'Status', 'last_login' => 'Last sign-in', 'password' => 'New password', 'avatar' => 'Avatar'],
    'status' => ['active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted'],
    'widgets' => ['active' => ['title' => 'Active users', 'description' => 'Current number of active administrator accounts.', 'metric' => 'active users']],
    'permissions' => ['view' => 'View users', 'create' => 'Create users', 'edit' => 'Edit users', 'disable' => 'Disable users', 'delete' => 'Delete users', 'restore' => 'Restore users', 'manage_roles' => 'Manage user roles', 'manage_permissions' => 'Manage user permissions'],
    'actions' => ['create' => 'Add user', 'edit' => 'Edit', 'activate' => 'Activate', 'deactivate' => 'Deactivate', 'delete' => 'Delete', 'restore' => 'Restore', 'activated' => 'User was activated.', 'deactivated' => 'User was deactivated.', 'deleted' => 'User deleted.', 'restored' => 'User restored.'],
    'editor' => [
        'title' => 'Edit user', 'description' => 'Manage the user account, role and access.', 'create_title' => 'Add user', 'create_description' => 'Create a new user account and assign a role.',
        'active' => 'Active account', 'active_help' => 'An inactive user cannot sign in.', 'version' => 'Record version', 'external_profile_synced' => 'Profile details are synchronized from an external identity.',
        'roles_help' => 'Choose exactly one role. The role determines the user\'s effective permissions.', 'roles_read_only' => 'You do not have permission to change the assigned role.', 'role_placeholder' => 'Choose a role',
        'password_help' => 'The password changes only after you explicitly open this section.', 'create_password_help' => 'A new local account requires a password.', 'change_password' => 'Change password', 'password_show' => 'Show password', 'password_hide' => 'Hide password', 'avatar_upload' => 'Upload or change avatar', 'avatar_remove' => 'Remove avatar', 'avatar_help' => 'JPEG, PNG or WebP. The uploaded image is validated and stored as the user avatar.',
        'permissions_help' => 'Permissions are inherited from the role. Changing a switch creates an exception for this user.', 'higher_administration_required' => 'Some permissions for this user can only be managed by a higher administration level.', 'no_permissions' => 'The selected role grants no permissions.', 'no_identities' => 'The user has no linked external identity.', 'saved' => 'User saved.', 'created' => 'User created.', 'profile_locked_by' => '{name} is currently editing their profile.', 'two_factor' => 'Two-factor authentication', 'two_factor_unavailable' => 'This feature is not available yet.', 'summary' => ['created_at' => 'Created', 'last_login' => 'Last sign-in'], 'permission_inherited_allow' => 'Allowed by role', 'permission_inherited_deny' => 'Not allowed by role', 'permission_allow' => 'Explicitly allowed for user', 'permission_deny' => 'Explicitly denied for user', 'permission_source_role' => 'Role', 'permission_source_override' => 'Override', 'permission_count_label' => 'Selected permissions', 'permission_select_all' => 'All permissions',
        'tabs' => ['basic' => 'Basic information', 'permissions' => 'Permissions'],
        'sections' => ['account' => 'Account', 'profile' => 'Profile', 'access' => 'Access and role', 'security' => 'Password', 'identities' => 'Linked identities', 'permissions' => 'Permissions'],
    ],
    'confirm' => ['activate' => 'Do you really want to activate this user?', 'deactivate' => 'Do you really want to deactivate this user?', 'delete' => 'Do you really want to delete this user?', 'restore' => 'Do you really want to restore this user?'],
    'notifications' => [
        'created' => '{actor} created user {user}.',
        'role_changed' => '{actor} changed the role for user {user}.',
        'permissions_changed' => '{actor} changed permissions for user {user}.',
        'activated' => '{actor} activated user {user}.',
        'deactivated' => '{actor} deactivated user {user}.',
        'deleted' => '{actor} deleted user {user}.',
        'restored' => '{actor} restored user {user}.',
    ],
    'audit' => [
        'events' => [
            'created' => 'Created user {user}.',
            'updated' => 'Updated profile for user {user}.',
            'password_changed' => 'Changed password for user {user}.',
            'role_changed' => 'Changed role for user {user}.',
            'permissions_changed' => 'Changed permissions for user {user}.',
            'activated' => 'Activated user {user}.',
            'deactivated' => 'Deactivated user {user}.',
            'deleted' => 'Deleted user {user}.',
            'restored' => 'Restored user {user}.',
        ],
    ],
    'validation' => ['first_name_required' => 'Enter a first name.', 'first_name_max_length' => 'First name can be at most 100 characters.', 'last_name_required' => 'Enter a last name.', 'last_name_max_length' => 'Last name can be at most 100 characters.', 'phone_max_length' => 'Phone can be at most 35 characters.', 'email_required' => 'Enter an email address.', 'email_invalid' => 'Enter a valid email address.', 'email_max_length' => 'Email can be at most 254 characters.', 'email_taken' => 'Another user already uses this email address.', 'status_required' => 'Select an account status.', 'status_invalid' => 'Account status is invalid.', 'status_not_manageable' => 'You do not have permission to change the account status.', 'version_required' => 'The record version is required.', 'version_invalid' => 'The record version is invalid.', 'role_required' => 'Choose a role.', 'role_invalid' => 'The selected role is invalid.', 'role_not_assignable' => 'You cannot assign the selected role.', 'permission_invalid' => 'The selected permission is invalid.', 'permissions_not_manageable' => 'You do not have permission to manage user permissions.', 'permission_not_delegable' => 'You cannot grant a permission you do not have.', 'superadmin_overrides_forbidden' => 'Superadmin permissions cannot use overrides.', 'cannot_change_own_authorization' => 'You cannot change your own role or permissions.', 'password_required' => 'Enter a password.', 'password_min_length' => 'Password must contain at least 12 characters.', 'cannot_deactivate_self' => 'You cannot deactivate your own account.', 'cannot_delete_self' => 'You cannot delete your own account.', 'cannot_delete_superadmin' => 'Only another superadmin can delete a superadmin.', 'last_administrator' => 'The last active administrator cannot lose administrator access.', 'user_not_deleted' => 'The user is not deleted.'],
];

$catalog['validation']['external_profile_readonly'] = 'The profile of a user with external sign-in cannot be changed';
$catalog['validation']['external_password_forbidden'] = 'A user with external sign-in cannot be assigned a local password';

return $catalog;
