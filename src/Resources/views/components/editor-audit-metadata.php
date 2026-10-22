<?php

declare(strict_types=1);

/**
 * @var Lemonade\Framework\View\ViewHelpers $helpers
 * @var list<array{labelKey:string,value:string,detail:string|null,wide?:bool,current?:bool}> $metadata
 */
?>
<dl class="lm-editor-audit-metadata">
    <?php foreach ($metadata as $item): ?>
        <div class="lm-editor-audit-metadata-item<?= ($item['wide'] ?? false) === true ? ' lm-editor-audit-metadata-item--wide' : '' ?><?= ($item['current'] ?? false) === true ? ' lm-editor-audit-metadata-item--current' : '' ?>">
            <dt data-lemonade-i18n="<?= e($item['labelKey']) ?>"><?= e($helpers->lang($item['labelKey'])) ?></dt>
            <dd>
                <strong><?= e($item['value'] === '' ? '—' : $item['value']) ?></strong>
                <?php if ($item['detail'] !== null): ?>
                    <span class="lm-editor-audit-metadata-detail"><?= e($item['detail'] === '' ? '—' : $item['detail']) ?></span>
                <?php endif; ?>
            </dd>
        </div>
    <?php endforeach; ?>
</dl>
