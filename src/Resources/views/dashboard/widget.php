<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var View $this */
/** @var ViewHelpers $helpers */
/** @var array{code:string,translationGroup:string,titleKey:string,title:string,icon:string|null,size:string,supportedSizes:list<string>,membership:string,managed:bool,protection:string|null,removable:bool,skeleton:string,position:int,contentEndpoint:string} $widget */

$size = in_array($widget['size'], ['small', 'medium', 'large', 'full'], true) ? $widget['size'] : 'medium';
?>
<article
    class="lm-dashboard-widget lm-dashboard-widget--<?= e($size) ?>"
    data-lemonade-dashboard-widget
    data-lemonade-widget-code="<?= e($widget['code']) ?>"
    data-lemonade-widget-source="<?= e($widget['contentEndpoint']) ?>"
    data-lemonade-widget-skeleton="<?= e($widget['skeleton']) ?>"
    data-lemonade-widget-membership="<?= e($widget['membership']) ?>"
    data-lemonade-widget-managed="<?= $widget['managed'] ? 'true' : 'false' ?>"
    data-lemonade-widget-protection="<?= e($widget['protection'] ?? '') ?>"
    data-lemonade-sortable-item="<?= e($widget['code']) ?>"
    aria-busy="true"
>
    <header class="lm-dashboard-widget-header">
        <div class="lm-dashboard-widget-heading">
            <?php if ($widget['icon'] !== null): ?>
                <span class="lm-dashboard-widget-icon"><i class="<?= e((AdminIcon::tryFrom($widget['icon']) ?? AdminIcon::Circle)->cssClass()) ?>" aria-hidden="true"></i></span>
            <?php endif; ?>
            <h2 data-lemonade-i18n="<?= e($widget['titleKey']) ?>"><?= e($widget['title']) ?></h2>
        </div>
        <div class="lm-dashboard-widget-actions">
            <button class="icon-button lm-dashboard-widget-refresh" type="button" data-lemonade-dashboard-widget-refresh aria-label="<?= e($helpers->lang('admin.dashboard.widgets.refresh')) ?>" title="<?= e($helpers->lang('admin.dashboard.widgets.refresh')) ?>" data-lemonade-i18n-aria-label="admin.dashboard.widgets.refresh" data-lemonade-i18n-title="admin.dashboard.widgets.refresh">
                <i class="<?= e(AdminIcon::ArrowClockwise->cssClass()) ?>" aria-hidden="true"></i>
            </button>
            <div class="lm-dashboard-widget-customize-actions" data-lemonade-dashboard-widget-customize-actions hidden>
                <button class="icon-button lm-dashboard-widget-drag" type="button" data-lemonade-sortable-handle aria-label="<?= e($helpers->lang('admin.dashboard.widgets.drag')) ?>" data-lemonade-i18n-aria-label="admin.dashboard.widgets.drag"><i class="<?= e(AdminIcon::GripVertical->cssClass()) ?>" aria-hidden="true"></i></button>
                <div data-lemonade-dropdown>
                    <button class="icon-button" type="button" data-lemonade-dropdown-trigger aria-expanded="false" aria-label="<?= e($helpers->lang('admin.common.actions')) ?>" data-lemonade-i18n-aria-label="admin.common.actions"><i class="<?= e(AdminIcon::ThreeDots->cssClass()) ?>" aria-hidden="true"></i></button>
                    <div class="lm-dropdown-menu lm-dashboard-widget-menu" data-lemonade-dropdown-panel hidden>
                        <button class="lm-dropdown-item" type="button" data-lemonade-dashboard-widget-move="up"><i class="<?= e(AdminIcon::ArrowUp->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.dashboard.widgets.moveUp"><?= e($helpers->lang('admin.dashboard.widgets.moveUp')) ?></span></button>
                        <button class="lm-dropdown-item" type="button" data-lemonade-dashboard-widget-move="down"><i class="<?= e(AdminIcon::ArrowDown->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.dashboard.widgets.moveDown"><?= e($helpers->lang('admin.dashboard.widgets.moveDown')) ?></span></button>
                        <div class="lm-dropdown-divider"></div>
                        <?php foreach ($widget['supportedSizes'] as $supportedSize): ?>
                            <button class="lm-dropdown-item<?= $supportedSize === $size ? ' is-active' : '' ?>" type="button" data-lemonade-dashboard-widget-size="<?= e($supportedSize) ?>"><span data-lemonade-i18n="admin.dashboard.widgets.size.<?= e($supportedSize) ?>"><?= e($helpers->lang('admin.dashboard.widgets.size.' . $supportedSize)) ?></span></button>
                        <?php endforeach; ?>
                        <?php if ($widget['removable']): ?>
                            <div class="lm-dropdown-divider"></div>
                            <button class="lm-dropdown-item lm-dashboard-widget-remove" type="button" data-lemonade-dashboard-widget-remove><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.dashboard.widgets.remove"><?= e($helpers->lang('admin.dashboard.widgets.remove')) ?></span></button>
                        <?php else: ?>
                            <div class="lm-dropdown-divider"></div>
                            <span class="lm-dashboard-widget-managed"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.dashboard.widgets.managed"><?= e($helpers->lang('admin.dashboard.widgets.managed')) ?></span></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="lm-dashboard-widget-body" data-lemonade-dashboard-widget-body>
        <?= $this->partial('admin::dashboard.widget-loading', ['skeleton' => $widget['skeleton']]) ?>
    </div>
    <footer class="lm-dashboard-widget-footer" data-lemonade-dashboard-widget-footer hidden></footer>
</article>
