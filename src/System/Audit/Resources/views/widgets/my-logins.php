<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;

/**
 * @var list<array{createdAt:string,method:string,provider:string|null}> $items
 */
?>
<ul class="lm-activity-list">
    <?php foreach ($items as $item): ?>
        <li class="lm-activity-item">
            <span class="lm-activity-icon"><i class="<?= e(AdminIcon::ShieldCheck->cssClass()) ?>" aria-hidden="true"></i></span>
            <div class="lm-activity-content">
                <p class="lm-activity-message"><strong><?= e($item['method']) ?></strong><?php if ($item['provider'] !== null): ?> <span class="lm-my-logins-provider">(<?= e($item['provider']) ?>)</span><?php endif; ?></p>
                <time class="lm-my-logins-time" datetime="<?= e($item['createdAt']) ?>" data-lemonade-i18n-datetime><?= e($item['createdAt']) ?></time>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
