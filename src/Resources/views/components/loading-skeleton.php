<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var string $variant */
/** @var int $rows */
/** @var string $loadingTextKey */

$isStat = $variant === 'stat';
$rowCount = $rows > 0 ? $rows : 4;
?>
<div class="lm-loading-skeleton lm-loading-skeleton--<?= $isStat ? 'stat' : 'list' ?>"<?= $isStat ? '' : ' data-lm-loading-skeleton-rows="' . e((string) $rowCount) . '"' ?> role="status">
    <span class="visually-hidden" data-lemonade-i18n="<?= e($loadingTextKey) ?>"><?= e($helpers->lang($loadingTextKey)) ?></span>
    <?php if ($isStat): ?>
        <span class="lm-loading-skeleton-block lm-loading-skeleton-block--metric" aria-hidden="true"></span>
        <span class="lm-loading-skeleton-block lm-loading-skeleton-block--label" aria-hidden="true"></span>
    <?php else: ?>
        <?php for ($row = 0; $row < $rowCount; $row++): ?>
            <div class="lm-loading-skeleton-row" aria-hidden="true"><span class="lm-loading-skeleton-avatar"></span><span class="lm-loading-skeleton-copy"><span class="lm-loading-skeleton-block"></span><span class="lm-loading-skeleton-block lm-loading-skeleton-block--short"></span></span><span class="lm-loading-skeleton-block lm-loading-skeleton-block--trailing"></span></div>
        <?php endfor; ?>
    <?php endif; ?>
</div>
