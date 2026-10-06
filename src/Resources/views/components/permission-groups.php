<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var list<array<string,mixed>> $groups */
/** @var 'role'|'user' $permissionMode */
/** @var list<string> $selectedPermissions */
/** @var bool $disabled */
/** @var string $countLabel */
/** @var string $groupAllLabel */
/** @var string|null $groupAllLabelKey */

$permissionMode = $permissionMode ?? 'role';
$selectedPermissions = $selectedPermissions ?? [];
$disabled = $disabled ?? false;
$countLabel = $countLabel ?? '';
$groupAllLabel = $groupAllLabel ?? '';
$groupAllLabelKey = isset($groupAllLabelKey) && is_string($groupAllLabelKey) ? $groupAllLabelKey : null;
$selectedPermissionSet = array_fill_keys($selectedPermissions, true);
$isUserMode = $permissionMode === 'user';
$groupId = static function (string $moduleCode): string {
    $normalized = preg_replace('/[^a-z0-9_-]+/i', '-', $moduleCode);

    return 'permission-group-' . trim((string) $normalized, '-');
};
$permissionId = static function (string $code): string {
    $normalized = preg_replace('/[^a-z0-9_-]+/i', '-', $code);

    return 'permission-' . trim((string) $normalized, '-');
};
$isSelected = static function (array $permission) use ($isUserMode, $selectedPermissionSet): bool {
    return $isUserMode
        ? in_array($permission['state'] ?? 'inherited_deny', ['allow', 'inherited_allow'], true)
        : isset($selectedPermissionSet[$permission['code']]);
};
?>
<div class="lm-permission-groups" data-lemonade-permission-groups data-lemonade-permission-mode="<?= e($permissionMode) ?>" data-lemonade-permission-count-label="<?= e($countLabel) ?>" data-lemonade-permission-select-all-label="<?= e($groupAllLabel) ?>" data-lemonade-permission-select-all-label-key="<?= e((string) $groupAllLabelKey) ?>"<?php if ($isUserMode): ?> data-lemonade-permission-role-label="<?= e($helpers->lang('users.editor.permission_source_role')) ?>" data-lemonade-permission-override-label="<?= e($helpers->lang('users.editor.permission_source_override')) ?>" data-lemonade-permission-inherited-allow-label="<?= e($helpers->lang('users.editor.permission_inherited_allow')) ?>" data-lemonade-permission-inherited-deny-label="<?= e($helpers->lang('users.editor.permission_inherited_deny')) ?>" data-lemonade-permission-allow-label="<?= e($helpers->lang('users.editor.permission_allow')) ?>" data-lemonade-permission-deny-label="<?= e($helpers->lang('users.editor.permission_deny')) ?>"<?php endif; ?>>
    <?php foreach ($groups as $group): ?>
        <?php
        $permissions = $group['permissions'];
        $selectedCount = count(array_filter($permissions, $isSelected));
        $totalCount = count($permissions);
        $headingId = $groupId((string) $group['moduleCode']);
        ?>
        <section class="lm-permission-group" data-lemonade-permission-group aria-labelledby="<?= e($headingId) ?>">
            <?php $groupToggleId = $headingId . '-all'; ?>
            <div class="lm-permission-group-header">
                <h3 class="lm-permission-group-title" id="<?= e($headingId) ?>">
                    <?php if (is_string($group['icon'] ?? null) && $group['icon'] !== ''): ?><i class="lm-permission-group-icon <?= e($group['icon']) ?>" aria-hidden="true"></i><?php endif; ?>
                    <span<?= is_string($group['labelKey'] ?? null) && $group['labelKey'] !== '' ? ' data-lemonade-i18n="' . e($group['labelKey']) . '"' : '' ?>><?= e((string) $group['label']) ?></span>
                </h3>
                <div class="lm-permission-group-actions">
                    <span class="lm-permission-group-count" data-lemonade-permission-count data-selected-count="<?= e((string) $selectedCount) ?>" data-total-count="<?= e((string) $totalCount) ?>" aria-live="polite" aria-label="<?= e($countLabel) ?>: <?= e((string) $selectedCount) ?> / <?= e((string) $totalCount) ?>"><?= e((string) $selectedCount) ?> / <?= e((string) $totalCount) ?></span>
                    <span class="lm-permission-group-select-all">
                        <input class="form-check-input lm-permission-group-toggle" id="<?= e($groupToggleId) ?>" type="checkbox"<?= $selectedCount === $totalCount && $totalCount > 0 ? ' checked' : '' ?><?= $disabled || $totalCount === 0 ? ' disabled' : '' ?><?= $disabled ? ' data-lemonade-permission-group-disabled' : '' ?> data-lemonade-permission-group-toggle aria-controls="<?= e($headingId) ?>-permissions">
                        <label class="form-check-label" for="<?= e($groupToggleId) ?>"<?= is_string($groupAllLabelKey) && $groupAllLabelKey !== '' ? ' data-lemonade-i18n="' . e($groupAllLabelKey) . '"' : '' ?>><?= e($groupAllLabel) ?></label>
                    </span>
                </div>
            </div>
            <div class="lm-permission-grid" id="<?= e($headingId) ?>-permissions">
                <?php foreach ($permissions as $permission): ?>
                    <?php
                    $code = (string) $permission['code'];
                    $id = $permissionId($code);
                    $state = (string) ($permission['state'] ?? '');
                    $isInherited = $isUserMode && str_starts_with($state, 'inherited');
                    $checked = $isSelected($permission);
                    $requires = is_array($permission['requires'] ?? null) ? array_values(array_filter($permission['requires'], 'is_string')) : [];
                    $requiresAttribute = implode(',', $requires);
                    ?>
                    <div class="lm-permission-item" data-lemonade-permission-item>
                        <?php if ($isUserMode): ?>
                            <input type="hidden" name="permission_overrides[<?= e($code) ?>]" value="<?= $isInherited ? '' : e($state) ?>"<?= $isInherited ? ' disabled' : '' ?> data-lemonade-permission-input>
                            <input class="form-check-input" id="<?= e($id) ?>" type="checkbox"<?= $checked ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?> data-lemonade-permission-toggle data-lemonade-permission-code="<?= e($code) ?>" data-lemonade-permission-requires="<?= e($requiresAttribute) ?>"<?= $isInherited ? ' data-lemonade-permission-inherited-state="' . e($state) . '"' : '' ?> data-lemonade-permission-state="<?= e($state) ?>" aria-describedby="<?= e($id) ?>-state">
                        <?php else: ?>
                            <input type="hidden" name="permissions[<?= e($code) ?>]" value="0">
                            <input class="form-check-input" id="<?= e($id) ?>" name="permissions[<?= e($code) ?>]" type="checkbox" value="1"<?= $checked ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?> data-lemonade-field="permissions" data-lemonade-permission-toggle data-lemonade-permission-code="<?= e($code) ?>" data-lemonade-permission-requires="<?= e($requiresAttribute) ?>">
                        <?php endif; ?>
                        <label class="form-check-label" for="<?= e($id) ?>" data-lemonade-i18n="<?= e((string) $permission['labelKey']) ?>"><?= e((string) $permission['label']) ?></label>
                        <?php if ($isUserMode): ?>
                            <span class="lm-permission-source<?= $isInherited ? ' is-inherited' : ' is-override' ?>" data-lemonade-permission-source>
                                <?php if ($isInherited): ?><i class="bi bi-lock" aria-hidden="true"></i><?php endif; ?><span data-lemonade-permission-source-label><?= e($isInherited ? $helpers->lang('users.editor.permission_source_role') : $helpers->lang('users.editor.permission_source_override')) ?></span>
                            </span>
                            <span class="visually-hidden" id="<?= e($id) ?>-state" data-lemonade-permission-state-label data-lemonade-i18n="users.editor.permission_<?= e($state) ?>"><?= e($helpers->lang('users.editor.permission_' . $state)) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
