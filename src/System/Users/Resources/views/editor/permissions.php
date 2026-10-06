<?php

declare(strict_types=1);

use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var View $this
 */
/**
 * @var list<array<string, mixed>> $permissionGroups
 */
/**
 * @var bool $permissionEditable
 */
/**
 * @var bool $hasProtectedAuthority
 */
/**
 * @var string $actionUrl
 */

$effectivePermissionCodes = [];
foreach ($permissionGroups as $permissionGroup) {
    foreach ($permissionGroup['permissions'] as $permission) {
        if (in_array($permission['state'], ['allow', 'inherited_allow'], true)) {
            $effectivePermissionCodes[] = (string) $permission['code'];
        }
    }
}
?>
<section class="card lm-card" data-lemonade-permission-overrides data-lemonade-permission-groups-root data-lemonade-dirty-state-resettable data-lemonade-permission-disabled-attribute="<?= $permissionEditable ? '' : ' disabled' ?>" data-lemonade-preview-url="<?= e($actionUrl) ?>" data-lemonade-preview-action="permissions-preview" data-lemonade-permission-editable="<?= $permissionEditable ? 'true' : 'false' ?>">
    <div class="card-body">
        <h2 class="h5 mb-2" data-lemonade-i18n="users.editor.sections.permissions"><?= e($helpers->lang('users.editor.sections.permissions')) ?></h2>
        <p class="lm-form-help mb-4" data-lemonade-i18n="users.editor.permissions_help"><?= e($helpers->lang('users.editor.permissions_help')) ?></p>
        <?php if ($hasProtectedAuthority): ?>
            <p class="lm-form-help" data-lemonade-i18n="users.editor.higher_administration_required"><?= e($helpers->lang('users.editor.higher_administration_required')) ?></p>
        <?php endif; ?>
        <?= $this->partial('admin::components.permission-groups', ['groups' => $permissionGroups, 'permissionMode' => 'user', 'selectedPermissions' => [], 'disabled' => !$permissionEditable, 'countLabel' => $helpers->lang('users.editor.permission_count_label'), 'groupAllLabel' => $helpers->lang('users.editor.permission_select_all'), 'groupAllLabelKey' => 'users.editor.permission_select_all']) ?>
    </div>
</section>
<?php if ($permissionEditable): ?>
    <noscript><style>[data-lemonade-permission-groups-root]{display:none}</style><section class="card lm-card mt-4"><div class="card-body"><h2 class="h5 mb-3" data-lemonade-i18n="users.editor.sections.permissions"><?= e($helpers->lang('users.editor.sections.permissions')) ?></h2><?= $this->partial('admin::components.permission-groups', ['groups' => $permissionGroups, 'permissionMode' => 'role', 'selectedPermissions' => $effectivePermissionCodes, 'disabled' => false, 'countLabel' => $helpers->lang('users.editor.permission_count_label'), 'groupAllLabel' => $helpers->lang('users.editor.permission_select_all'), 'groupAllLabelKey' => 'users.editor.permission_select_all']) ?></div></section></noscript>
<?php endif; ?>
