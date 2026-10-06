<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var list<array{actor:string,action:string,icon:string,module:string,createdAt:string}> $items
 */
?>
<ul class="lm-activity-list">
    <?php foreach ($items as $item): ?>
        <li class="lm-activity-item">
            <span class="lm-activity-icon"><i class="<?= e((AdminIcon::tryFrom($item['icon']) ?? AdminIcon::ClockHistory)->cssClass()) ?>" aria-hidden="true"></i></span>
            <div class="lm-activity-content">
                <p class="lm-activity-message"><strong><?= e($item['actor']) ?></strong> <?= e($item['action']) ?></p>
                <small class="lm-activity-meta"><?= e($item['module']) ?></small>
            </div>
            <span class="lm-activity-trailing"><time class="lm-activity-time" datetime="<?= e($item['createdAt']) ?>" data-lemonade-i18n-datetime><?= e($item['createdAt']) ?></time></span>
        </li>
    <?php endforeach; ?>
</ul>
