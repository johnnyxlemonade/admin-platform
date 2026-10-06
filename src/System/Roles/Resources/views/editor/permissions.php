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
 * @var list<array<string, mixed>> $groups
 */
/**
 * @var list<string> $selectedPermissions
 */
/**
 * @var bool $disabled
 */
/**
 * @var bool $rootProtected
 */
?>
<?php if ($rootProtected): ?>
    <p class="lm-form-help" data-lemonade-i18n="roles.editor.root_protected"><?= e($helpers->lang('roles.editor.root_protected')) ?></p>
<?php endif; ?>
<div data-lemonade-permission-groups-root data-lemonade-dirty-state-resettable>
    <?= $this->partial('admin::components.permission-groups', [
        'groups' => $groups,
        'permissionMode' => 'role',
        'selectedPermissions' => $selectedPermissions,
        'disabled' => $disabled,
        'countLabel' => $helpers->lang('roles.editor.permission_count_label'),
        'groupAllLabel' => $helpers->lang('roles.editor.permission_select_all'),
        'groupAllLabelKey' => 'roles.editor.permission_select_all',
    ]) ?>
</div>
