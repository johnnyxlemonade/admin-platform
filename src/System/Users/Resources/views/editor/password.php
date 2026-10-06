<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var bool $isCreate
 */
?>
<div data-lemonade-conditional>
    <?php if (!$isCreate): ?>
        <input class="btn-check" id="system-users-change-password" type="checkbox" value="change" data-lemonade-conditional-control>
        <label class="btn btn-light lm-button-with-icon mb-3" for="system-users-change-password">
            <i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i>
            <span data-lemonade-i18n="users.editor.change_password"><?= e($helpers->lang('users.editor.change_password')) ?></span>
        </label>
    <?php endif; ?>
    <div data-lemonade-conditional-panel="<?= $isCreate ? '' : 'change' ?>"<?= $isCreate ? '' : ' hidden' ?>>
        <div class="lm-form-group">
            <label class="lm-form-label" for="system-users-password" data-lemonade-i18n="users.fields.password"><?= e($helpers->lang('users.fields.password')) ?></label>
            <div class="lemonade-auth">
                <div class="lm-auth-field">
                    <span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i></span>
                    <input class="form-control" id="system-users-password" name="local_password" type="password" data-lemonade-password-input autocomplete="new-password"<?= $isCreate ? ' required' : '' ?>>
                    <button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('users.editor.password_show')) ?>" data-lemonade-i18n-aria-label="users.editor.password_show" data-lemonade-password-show-key="users.editor.password_show" data-lemonade-password-hide-key="users.editor.password_hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
