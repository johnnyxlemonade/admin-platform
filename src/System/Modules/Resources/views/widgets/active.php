<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var list<array{label:string,icon:string|null,route:string,routeParameters:array<string,bool|float|int|string|null>}> $modules
 */
?>
<ul class="lm-dashboard-module-list">
    <?php foreach ($modules as $module): ?>
        <li>
            <span class="lm-dashboard-module-icon"><i class="<?= e((AdminIcon::tryFrom($module['icon'] ?? '') ?? AdminIcon::Boxes)->cssClass()) ?>" aria-hidden="true"></i></span>
            <div class="lm-dashboard-module-content">
                <a href="<?= e($helpers->url($module['route'], $module['routeParameters'])) ?>"><?= e($module['label']) ?></a>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
