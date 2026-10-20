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
$allPermissions = $groups === []
    ? []
    : array_merge(...array_map(static fn(array $group): array => $group['permissions'], $groups));
$selectedCount = count(array_filter($allPermissions, $isSelected));
$totalCount = count($allPermissions);
?>
<div class="lm-permission-groups" data-lemonade-permission-groups data-lemonade-permission-mode="<?= e($permissionMode) ?>" data-lemonade-permission-count-label="<?= e($countLabel) ?>" data-lemonade-permission-select-all-label="<?= e($groupAllLabel) ?>" data-lemonade-permission-select-all-label-key="<?= e((string) $groupAllLabelKey) ?>" data-lemonade-permission-system-title="<?= e($helpers->lang('admin.permissions.system.title')) ?>" data-lemonade-permission-system-description="<?= e($helpers->lang('admin.permissions.system.description')) ?>" data-lemonade-permission-modules-title="<?= e($helpers->lang('admin.permissions.modules.title')) ?>" data-lemonade-permission-modules-description="<?= e($helpers->lang('admin.permissions.modules.description')) ?>"<?php if ($isUserMode): ?> data-lemonade-permission-role-label="<?= e($helpers->lang('users.editor.permission_source_role')) ?>" data-lemonade-permission-override-label="<?= e($helpers->lang('users.editor.permission_source_override')) ?>" data-lemonade-permission-inherited-allow-label="<?= e($helpers->lang('users.editor.permission_inherited_allow')) ?>" data-lemonade-permission-inherited-deny-label="<?= e($helpers->lang('users.editor.permission_inherited_deny')) ?>" data-lemonade-permission-allow-label="<?= e($helpers->lang('users.editor.permission_allow')) ?>" data-lemonade-permission-deny-label="<?= e($helpers->lang('users.editor.permission_deny')) ?>"<?php endif; ?>>
    <div class="lm-permission-layout">
        <div class="lm-permission-main">
            <div class="lm-permission-toolbar" data-lemonade-permission-controls hidden>
                <div class="lm-permission-filter-control">
                    <span class="lm-permission-filter-icon"><i class="<?= e(AdminIcon::Search->cssClass()) ?>" aria-hidden="true"></i></span>
                    <input class="form-control lm-permission-filter" type="search" autocomplete="off" placeholder="<?= e($helpers->lang('admin.permissions.search_placeholder')) ?>" aria-label="<?= e($helpers->lang('admin.permissions.search')) ?>" data-lemonade-permission-filter data-lemonade-i18n-placeholder="admin.permissions.search_placeholder" data-lemonade-i18n-aria-label="admin.permissions.search">
                </div>
                <div class="lm-permission-expand-actions">
                    <button class="btn btn-light" type="button" data-lemonade-permission-toggle-all data-lemonade-permission-expand-label="<?= e($helpers->lang('admin.permissions.expand_all')) ?>" data-lemonade-permission-collapse-label="<?= e($helpers->lang('admin.permissions.collapse_all')) ?>" aria-label="<?= e($helpers->lang('admin.permissions.expand_all')) ?>">
                        <i class="<?= e(AdminIcon::ChevronDown->cssClass()) ?>" data-lemonade-permission-expand-icon aria-hidden="true"></i>
                        <i class="<?= e(AdminIcon::ChevronUp->cssClass()) ?>" data-lemonade-permission-collapse-icon aria-hidden="true" hidden></i>
                        <span data-lemonade-permission-toggle-all-label data-lemonade-i18n="admin.permissions.expand_all"><?= e($helpers->lang('admin.permissions.expand_all')) ?></span>
                    </button>
                </div>
            </div>
            <div class="lm-permission-sections" data-lemonade-permission-sections>
                <?php foreach ($sections as $section): ?>
                    <?php if ($section['groups'] === []): continue; endif; ?>
                    <?php
                    $sectionPermissions = array_merge(...array_map(static fn(array $group): array => $group['permissions'], $section['groups']));
                    $sectionSelectedCount = count(array_filter($sectionPermissions, $isSelected));
                    $sectionTotalCount = count($sectionPermissions);
                    $sectionId = 'permission-section-' . $section['scope'];
                    $sectionToggleId = $sectionId . '-all';
                    ?>
                    <section class="lm-permission-section" data-lemonade-permission-section data-lemonade-permission-section-scope="<?= e($section['scope']) ?>" aria-labelledby="<?= e($sectionId) ?>">
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
                                $groupSelectedCount = count(array_filter($permissions, $isSelected));
                                $groupTotalCount = count($permissions);
                                $headingId = $groupId((string) $group['moduleCode']);
                                $groupToggleId = $headingId . '-all';
                                $groupBodyId = $headingId . '-permissions';
                                ?>
                                <section class="lm-permission-group" data-lemonade-permission-group data-lemonade-permission-module-code="<?= e((string) $group['moduleCode']) ?>" aria-labelledby="<?= e($headingId) ?>">
                                    <div class="lm-permission-group-header">
                                        <button class="lm-permission-group-heading" type="button" data-lemonade-permission-group-collapse aria-expanded="false" aria-controls="<?= e($groupBodyId) ?>">
                                            <?php if (is_string($group['icon'] ?? null) && $group['icon'] !== ''): ?><span class="lm-permission-group-icon"><i class="<?= e($group['icon']) ?>" aria-hidden="true"></i></span><?php endif; ?>
                                            <span class="lm-permission-group-copy">
                                                <span class="lm-permission-group-title" id="<?= e($headingId) ?>"><span<?= is_string($group['labelKey'] ?? null) && $group['labelKey'] !== '' ? ' data-lemonade-i18n="' . e($group['labelKey']) . '"' : '' ?>><?= e((string) $group['label']) ?></span></span>
                                                <?php if (is_string($group['description'] ?? null) && $group['description'] !== ''): ?>
                                                    <span class="lm-permission-group-description"<?= is_string($group['descriptionKey'] ?? null) && $group['descriptionKey'] !== '' ? ' data-lemonade-i18n="' . e($group['descriptionKey']) . '"' : '' ?>><?= e($group['description']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <i class="<?= e(AdminIcon::ChevronDown->cssClass()) ?> lm-permission-group-chevron" aria-hidden="true"></i>
                                        </button>
                                        <div class="lm-permission-group-actions">
                                            <span class="lm-permission-group-count" data-lemonade-permission-count data-selected-count="<?= e((string) $groupSelectedCount) ?>" data-total-count="<?= e((string) $groupTotalCount) ?>" aria-live="polite" aria-label="<?= e($countLabel) ?>: <?= e((string) $groupSelectedCount) ?> / <?= e((string) $groupTotalCount) ?>"><?= e((string) $groupSelectedCount) ?> / <?= e((string) $groupTotalCount) ?></span>
                                            <span class="lm-permission-group-select-all">
                                                <input class="form-check-input lm-permission-group-toggle" id="<?= e($groupToggleId) ?>" type="checkbox"<?= $groupSelectedCount === $groupTotalCount && $groupTotalCount > 0 ? ' checked' : '' ?><?= $disabled || $groupTotalCount === 0 ? ' disabled' : '' ?><?= $disabled ? ' data-lemonade-permission-group-disabled' : '' ?> data-lemonade-permission-group-toggle aria-controls="<?= e($groupBodyId) ?>">
                                                <label class="form-check-label" for="<?= e($groupToggleId) ?>"<?= is_string($groupAllLabelKey) && $groupAllLabelKey !== '' ? ' data-lemonade-i18n="' . e($groupAllLabelKey) . '"' : '' ?>><?= e($groupAllLabel) ?></label>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="lm-permission-group-body" id="<?= e($groupBodyId) ?>" data-lemonade-permission-group-body hidden>
                                        <div class="lm-permission-grid">
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
                                                            <?php if ($isInherited): ?><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i><?php endif; ?><span data-lemonade-permission-source-label><?= e($isInherited ? $helpers->lang('users.editor.permission_source_role') : $helpers->lang('users.editor.permission_source_override')) ?></span>
                                                        </span>
                                                        <span class="visually-hidden" id="<?= e($id) ?>-state" data-lemonade-permission-state-label data-lemonade-i18n="users.editor.permission_<?= e($state) ?>"><?= e($helpers->lang('users.editor.permission_' . $state)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
            <p class="lm-permission-empty" data-lemonade-permission-empty data-lemonade-i18n="admin.permissions.no_results" hidden><?= e($helpers->lang('admin.permissions.no_results')) ?></p>
        </div>
        <aside class="lm-permission-sidebar" aria-label="<?= e($helpers->lang('admin.permissions.summary')) ?>">
            <section class="lm-permission-summary">
                <h3 data-lemonade-i18n="admin.permissions.summary"><?= e($helpers->lang('admin.permissions.summary')) ?></h3>
                <p class="lm-permission-summary-value"><strong data-lemonade-permission-total-selected><?= e((string) $selectedCount) ?></strong><span aria-hidden="true"> / </span><span data-lemonade-permission-total><?= e((string) $totalCount) ?></span></p>
                <p class="lm-permission-summary-label"><?= e($countLabel) ?></p>
                <progress class="lm-permission-progress" max="<?= e((string) max(1, $totalCount)) ?>" value="<?= e((string) $selectedCount) ?>" data-lemonade-permission-progress aria-label="<?= e($countLabel) ?>"></progress>
            </section>
            <section class="lm-permission-quick-actions" data-lemonade-permission-controls hidden>
                <h3 data-lemonade-i18n="admin.permissions.quick_actions"><?= e($helpers->lang('admin.permissions.quick_actions')) ?></h3>
                <button class="btn btn-light" type="button" data-lemonade-permission-select-all<?= $disabled ? ' disabled data-lemonade-permission-controls-disabled' : '' ?>><i class="<?= e(AdminIcon::Check2All->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.permissions.select_all"><?= e($helpers->lang('admin.permissions.select_all')) ?></span></button>
                <button class="btn btn-light" type="button" data-lemonade-permission-clear-all<?= $disabled ? ' disabled data-lemonade-permission-controls-disabled' : '' ?>><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.permissions.clear_all"><?= e($helpers->lang('admin.permissions.clear_all')) ?></span></button>
            </section>
            <section class="lm-permission-module-overview" data-lemonade-permission-controls hidden>
                <h3 data-lemonade-i18n="admin.permissions.module_overview"><?= e($helpers->lang('admin.permissions.module_overview')) ?></h3>
                <div class="lm-permission-module-list" data-lemonade-permission-module-list></div>
            </section>
        </aside>
    </div>
</div>
