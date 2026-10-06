<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var bool $hasProtectedAuthority
 */
?>
<?php if ($hasProtectedAuthority): ?>
    <p class="lm-form-help" data-lemonade-i18n="users.editor.higher_administration_required"><?= e($helpers->lang('users.editor.higher_administration_required')) ?></p>
<?php endif; ?>
<div class="lm-form-check">
    <div class="form-check form-switch">
        <input class="form-check-input" id="system-users-two-factor" type="checkbox" disabled>
        <label class="form-check-label" for="system-users-two-factor" data-lemonade-i18n="users.editor.two_factor"><?= e($helpers->lang('users.editor.two_factor')) ?></label>
    </div>
    <span class="lm-form-help" data-lemonade-i18n="users.editor.two_factor_unavailable"><?= e($helpers->lang('users.editor.two_factor_unavailable')) ?></span>
</div>
