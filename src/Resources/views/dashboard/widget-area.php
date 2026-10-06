<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var View $this */
/** @var ViewHelpers $helpers */
/** @var list<array{code:string,translationGroup:string,titleKey:string,title:string,icon:string|null,size:string,supportedSizes:list<string>,membership:string,managed:bool,protection:string|null,removable:bool,skeleton:string,position:int,contentEndpoint:string}> $dashboardWidgets */
?>
<div data-lemonade-dashboard-widget-area>
    <?php if ($dashboardWidgets === []): ?>
        <div class="lm-dashboard-empty" role="status">
            <i class="<?= e(AdminIcon::Grid1x2->cssClass()) ?>" aria-hidden="true"></i>
            <p data-lemonade-i18n="admin.dashboard.widgets.emptyDashboard"><?= e($helpers->lang('admin.dashboard.widgets.emptyDashboard')) ?></p>
        </div>
    <?php else: ?>
        <div class="lm-dashboard-widget-grid">
            <?php foreach ($dashboardWidgets as $widget): ?>
                <?= $this->partial('admin::dashboard.widget', ['widget' => $widget]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
