<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var bool $activeEditable
 */
/**
 * @var string $activeValue
 */
?>
<input name="active" type="hidden" value="<?= e($activeValue) ?>" data-lemonade-action-fallback>
<?php if ($activeEditable): ?>
    <div class="lm-form-check">
        <div class="form-check form-switch">
            <input class="form-check-input" id="system-users-active" name="active" type="checkbox" value="1"<?= $activeValue === '1' ? ' checked' : '' ?> data-lemonade-field="active">
            <label class="form-check-label" for="system-users-active" data-lemonade-i18n="users.editor.active"><?= e($helpers->lang('users.editor.active')) ?></label>
        </div>
        <span class="lm-form-help" data-lemonade-i18n="users.editor.active_help"><?= e($helpers->lang('users.editor.active_help')) ?></span>
    </div>
<?php else: ?>
    <p class="lm-form-help mb-0"><span data-lemonade-i18n="users.editor.active"><?= e($helpers->lang('users.editor.active')) ?></span>: <?= e($helpers->lang($activeValue === '1' ? 'users.status.active' : 'users.status.inactive')) ?></p>
<?php endif; ?>
