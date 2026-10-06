<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var array{type:string,title:string,message:string,audience_type:string,role_ids:list<int>,user_ids:list<int>} $notification
 */
/**
 * @var list<array{id:int,code:string,name:string,is_super_admin:int}> $roles
 */
/**
 * @var array<string, mixed> $input
 */
/**
 * @var list<array{id:int,email:string}> $selectedUsers
 */
$value = static fn(string $key, mixed $default): mixed => $input[$key] ?? $default;
$roleValues = array_map('strval', (array) $value('role_ids', $notification['role_ids'] ?? []));
$userValues = array_map('strval', (array) $value('user_ids', $notification['user_ids'] ?? []));
$audienceType = $value('audience_type', $notification['audience_type']) === 'users' ? 'users' : 'roles';
$audienceUsersUrl = $helpers->url('admin.notifications.audience.users');
?>
<fieldset class="lm-form-group mb-4" data-lemonade-notification-audience><legend class="lm-form-label required" data-lemonade-i18n="notifications.fields.audience_type"><?= e($helpers->lang('notifications.fields.audience_type')) ?></legend><div class="form-check"><input class="form-check-input" id="notification-audience-roles" name="audience_type" type="radio" value="roles"<?= $audienceType === 'roles' ? ' checked' : '' ?> data-lemonade-field="audience_type" data-lemonade-notification-audience-type><label class="form-check-label" for="notification-audience-roles" data-lemonade-i18n="notifications.audience_type.roles"><?= e($helpers->lang('notifications.audience_type.roles')) ?></label></div><div class="form-check mb-3"><input class="form-check-input" id="notification-audience-users" name="audience_type" type="radio" value="users"<?= $audienceType === 'users' ? ' checked' : '' ?> data-lemonade-field="audience_type" data-lemonade-notification-audience-type><label class="form-check-label" for="notification-audience-users" data-lemonade-i18n="notifications.audience_type.users"><?= e($helpers->lang('notifications.audience_type.users')) ?></label></div><div class="lm-form-group" data-lemonade-notification-audience-target="roles"<?= $audienceType === 'roles' ? '' : ' hidden' ?>><label class="lm-form-label" for="notification-roles" data-lemonade-i18n="notifications.fields.roles"><?= e($helpers->lang('notifications.fields.roles')) ?></label><select id="notification-roles" class="form-select" name="role_ids[]" multiple data-lemonade-field="role_ids" data-lemonade-option-source="static" data-lemonade-searchable<?= $audienceType === 'roles' ? '' : ' disabled' ?>><?php foreach ($roles as $role): ?><option value="<?= e((string) $role['id']) ?>"<?= in_array((string) $role['id'], $roleValues, true) ? ' selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select><span class="lm-form-help" data-lemonade-i18n="notifications.editor.roles_help"><?= e($helpers->lang('notifications.editor.roles_help')) ?></span></div><div class="lm-form-group" data-lemonade-notification-audience-target="users"<?= $audienceType === 'users' ? '' : ' hidden' ?>><label class="lm-form-label" for="notification-users" data-lemonade-i18n="notifications.fields.users"><?= e($helpers->lang('notifications.fields.users')) ?></label><select id="notification-users" class="form-select lm-notification-user-select" name="user_ids[]" multiple size="6" data-lemonade-field="user_ids" data-lemonade-select data-lemonade-option-source="async" data-lemonade-source="<?= e($audienceUsersUrl) ?>" data-lemonade-min-length="0"<?= $audienceType === 'users' ? '' : ' disabled' ?>><?php foreach ($selectedUsers as $user): ?><option value="<?= e((string) $user['id']) ?>"<?= in_array((string) $user['id'], $userValues, true) ? ' selected' : '' ?>><?= e($user['email']) ?></option><?php endforeach; ?></select><span class="lm-form-help" data-lemonade-i18n="notifications.editor.users_help"><?= e($helpers->lang('notifications.editor.users_help')) ?></span></div></fieldset>
