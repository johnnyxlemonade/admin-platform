<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var list<array{section:string,modules:list<array{label:string,icon:string|null,route:string,routeParameters:array<string,bool|float|int|string|null>}>}> $groups
 */
?>
<div class="lm-permission-sections lm-dashboard-module-groups">
    <?php foreach ($groups as $group): ?>
        <?php $titleKey = 'modules.widgets.active.groups.' . $group['section']; ?>
        <section class="lm-permission-section" aria-labelledby="dashboard-modules-<?= e($group['section']) ?>">
            <div class="lm-permission-section-header">
                <div class="lm-permission-section-heading">
                    <span class="lm-dashboard-module-group-icon"><i class="<?= e(($group['section'] === 'system' ? AdminIcon::Gear : AdminIcon::Boxes)->cssClass()) ?>" aria-hidden="true"></i></span>
                    <h3 id="dashboard-modules-<?= e($group['section']) ?>" data-lemonade-i18n="<?= e($titleKey) ?>"><?= e($helpers->lang($titleKey)) ?></h3>
                </div>
            </div>
            <ul class="lm-dashboard-module-list">
                <?php foreach ($group['modules'] as $module): ?>
                    <li>
                        <span class="lm-dashboard-module-icon"><i class="<?= e((AdminIcon::tryFrom($module['icon'] ?? '') ?? AdminIcon::Boxes)->cssClass()) ?>" aria-hidden="true"></i></span>
                        <div class="lm-dashboard-module-content">
                            <a href="<?= e($helpers->url($module['route'], $module['routeParameters'])) ?>"><?= e($module['label']) ?></a>
                        </div>
                        <span class="lm-dashboard-module-chevron" aria-hidden="true"></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
