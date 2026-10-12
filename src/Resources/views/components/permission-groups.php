<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
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
$sections = [
    [
        'scope' => 'system',
        'titleKey' => 'admin.permissions.system.title',
        'descriptionKey' => 'admin.permissions.system.description',
        'icon' => AdminIcon::Gear,
        'groups' => array_values(array_filter($groups, static fn(array $group): bool => str_starts_with((string) $group['moduleCode'], 'system.'))),
    ],
    [
        'scope' => 'modules',
        'titleKey' => 'admin.permissions.modules.title',
        'descriptionKey' => 'admin.permissions.modules.description',
        'icon' => AdminIcon::Boxes,
        'groups' => array_values(array_filter($groups, static fn(array $group): bool => !str_starts_with((string) $group['moduleCode'], 'system.'))),
    ],
];
$previewLimit = 3;
?>
<div class="lm-permission-groups" data-lemonade-permission-groups data-lemonade-permission-mode="<?= e($permissionMode) ?>" data-lemonade-permission-count-label="<?= e($countLabel) ?>" data-lemonade-permission-select-all-label="<?= e($groupAllLabel) ?>" data-lemonade-permission-select-all-label-key="<?= e((string) $groupAllLabelKey) ?>" data-lemonade-permission-show-all-label="<?= e($helpers->lang('admin.permissions.show_all')) ?>" data-lemonade-permission-hide-label="<?= e($helpers->lang('admin.permissions.hide')) ?>" data-lemonade-permission-system-title="<?= e($helpers->lang('admin.permissions.system.title')) ?>" data-lemonade-permission-system-description="<?= e($helpers->lang('admin.permissions.system.description')) ?>" data-lemonade-permission-modules-title="<?= e($helpers->lang('admin.permissions.modules.title')) ?>" data-lemonade-permission-modules-description="<?= e($helpers->lang('admin.permissions.modules.description')) ?>"<?php if ($isUserMode): ?> data-lemonade-permission-role-label="<?= e($helpers->lang('users.editor.permission_source_role')) ?>" data-lemonade-permission-override-label="<?= e($helpers->lang('users.editor.permission_source_override')) ?>" data-lemonade-permission-inherited-allow-label="<?= e($helpers->lang('users.editor.permission_inherited_allow')) ?>" data-lemonade-permission-inherited-deny-label="<?= e($helpers->lang('users.editor.permission_inherited_deny')) ?>" data-lemonade-permission-allow-label="<?= e($helpers->lang('users.editor.permission_allow')) ?>" data-lemonade-permission-deny-label="<?= e($helpers->lang('users.editor.permission_deny')) ?>"<?php endif; ?>>
    <?php foreach ($sections as $section): ?>
        <?php if ($section['groups'] === []): continue; endif; ?>
        <?php
        $sectionPermissions = array_merge(...array_map(static fn(array $group): array => $group['permissions'], $section['groups']));
        $sectionSelectedCount = count(array_filter($sectionPermissions, $isSelected));
        $sectionTotalCount = count($sectionPermissions);
        $sectionId = 'permission-section-' . $section['scope'];
        $sectionToggleId = $sectionId . '-all';
        ?>
        <section class="lm-permission-section" data-lemonade-permission-section aria-labelledby="<?= e($sectionId) ?>">
            <header class="lm-permission-section-header">
                <div class="lm-permission-section-heading">
                    <span class="lm-permission-section-icon"><i class="<?= e($section['icon']->cssClass()) ?>" aria-hidden="true"></i></span>
                    <div>
                        <h3 id="<?= e($sectionId) ?>" data-lemonade-i18n="<?= e($section['titleKey']) ?>"><?= e($helpers->lang($section['titleKey'])) ?></h3>
                        <p data-lemonade-i18n="<?= e($section['descriptionKey']) ?>"><?= e($helpers->lang($section['descriptionKey'])) ?></p>
                    </div>
                </div>
                <div class="lm-permission-section-actions">
                    <span class="lm-permission-section-count" data-lemonade-permission-section-count data-selected-count="<?= e((string) $sectionSelectedCount) ?>" data-total-count="<?= e((string) $sectionTotalCount) ?>" aria-live="polite" aria-label="<?= e($countLabel) ?>: <?= e((string) $sectionSelectedCount) ?> / <?= e((string) $sectionTotalCount) ?>"><?= e((string) $sectionSelectedCount) ?> / <?= e((string) $sectionTotalCount) ?></span>
                    <span class="lm-permission-section-select-all">
                        <input class="form-check-input" id="<?= e($sectionToggleId) ?>" type="checkbox"<?= $sectionSelectedCount === $sectionTotalCount && $sectionTotalCount > 0 ? ' checked' : '' ?><?= $disabled ? ' disabled data-lemonade-permission-section-disabled' : '' ?> data-lemonade-permission-section-toggle aria-controls="<?= e($sectionId) ?>-groups">
                        <label class="form-check-label" for="<?= e($sectionToggleId) ?>"<?= is_string($groupAllLabelKey) && $groupAllLabelKey !== '' ? ' data-lemonade-i18n="' . e($groupAllLabelKey) . '"' : '' ?>><?= e($groupAllLabel) ?></label>
                    </span>
                </div>
            </header>
            <div class="lm-permission-section-groups" id="<?= e($sectionId) ?>-groups">
                <?php foreach ($section['groups'] as $group): ?>
                    <?php
                    $permissions = $group['permissions'];
                    $selectedCount = count(array_filter($permissions, $isSelected));
                    $totalCount = count($permissions);
                    $headingId = $groupId((string) $group['moduleCode']);
                    $groupToggleId = $headingId . '-all';
                    ?>
                    <section class="lm-permission-group" data-lemonade-permission-group aria-labelledby="<?= e($headingId) ?>">
                        <div class="lm-permission-group-header">
                            <div class="lm-permission-group-heading">
                                <?php if (is_string($group['icon'] ?? null) && $group['icon'] !== ''): ?><span class="lm-permission-group-icon"><i class="<?= e($group['icon']) ?>" aria-hidden="true"></i></span><?php endif; ?>
                                <h4 class="lm-permission-group-title" id="<?= e($headingId) ?>"><span<?= is_string($group['labelKey'] ?? null) && $group['labelKey'] !== '' ? ' data-lemonade-i18n="' . e($group['labelKey']) . '"' : '' ?>><?= e((string) $group['label']) ?></span></h4>
                            </div>
                            <div class="lm-permission-group-actions">
                                <span class="lm-permission-group-count" data-lemonade-permission-count data-selected-count="<?= e((string) $selectedCount) ?>" data-total-count="<?= e((string) $totalCount) ?>" aria-live="polite" aria-label="<?= e($countLabel) ?>: <?= e((string) $selectedCount) ?> / <?= e((string) $totalCount) ?>"><?= e((string) $selectedCount) ?> / <?= e((string) $totalCount) ?></span>
                                <span class="lm-permission-group-select-all">
                                    <input class="form-check-input lm-permission-group-toggle" id="<?= e($groupToggleId) ?>" type="checkbox"<?= $selectedCount === $totalCount && $totalCount > 0 ? ' checked' : '' ?><?= $disabled || $totalCount === 0 ? ' disabled' : '' ?><?= $disabled ? ' data-lemonade-permission-group-disabled' : '' ?> data-lemonade-permission-group-toggle aria-controls="<?= e($headingId) ?>-permissions">
                                    <label class="form-check-label" for="<?= e($groupToggleId) ?>"<?= is_string($groupAllLabelKey) && $groupAllLabelKey !== '' ? ' data-lemonade-i18n="' . e($groupAllLabelKey) . '"' : '' ?>><?= e($groupAllLabel) ?></label>
                                </span>
                            </div>
                        </div>
                        <div class="lm-permission-grid" id="<?= e($headingId) ?>-permissions">
                            <?php foreach ($permissions as $index => $permission): ?>
                                <?php
                                $code = (string) $permission['code'];
                                $id = $permissionId($code);
                                $state = (string) ($permission['state'] ?? '');
                                $isInherited = $isUserMode && str_starts_with($state, 'inherited');
                                $checked = $isSelected($permission);
                                $requires = is_array($permission['requires'] ?? null) ? array_values(array_filter($permission['requires'], 'is_string')) : [];
                                $requiresAttribute = implode(',', $requires);
                                ?>
                                <div class="lm-permission-item" data-lemonade-permission-item<?= $index >= $previewLimit ? ' hidden data-lemonade-permission-item-extra' : '' ?>>
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
                        <?php if ($totalCount > $previewLimit): ?>
                            <div class="lm-permission-group-footer">
                                <button class="lm-permission-group-expand" type="button" data-lemonade-permission-group-expand aria-expanded="false" aria-controls="<?= e($headingId) ?>-permissions" data-lemonade-show-all-label="<?= e($helpers->lang('admin.permissions.show_all', ['count' => $totalCount])) ?>" data-lemonade-hide-label="<?= e($helpers->lang('admin.permissions.hide')) ?>"><span data-lemonade-permission-group-expand-label><?= e($helpers->lang('admin.permissions.show_all', ['count' => $totalCount])) ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
