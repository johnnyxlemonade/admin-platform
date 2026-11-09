<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var int $activeUserCount
 */
?>
<div class="lm-dashboard-kpi">
    <div class="lm-dashboard-kpi-copy">
        <strong class="lm-dashboard-kpi-value"><?= e((string) $activeUserCount) ?></strong>
        <span class="lm-dashboard-kpi-label" data-lemonade-i18n="users.widgets.active.metric"><?= e($helpers->lang('users.widgets.active.metric')) ?></span>
    </div>
    <span class="lm-dashboard-kpi-decoration" aria-hidden="true">
        <i class="<?= e(AdminIcon::Person->cssClass()) ?>"></i>
        <i class="<?= e(AdminIcon::Person->cssClass()) ?>"></i>
        <i class="<?= e(AdminIcon::Person->cssClass()) ?>"></i>
    </span>
</div>
