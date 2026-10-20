<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var string|null $errorKey */
/** @var string|null $errorMessage */
?>
<h1 class="lm-auth-title" data-lemonade-i18n="auth.mfa.title"><?= e($helpers->lang('auth.mfa.title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="auth.mfa.challenge_description"><?= e($helpers->lang('auth.mfa.challenge_description')) ?></p>
<form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.login.mfa.verify')) ?>" data-lemonade-auth-login data-lemonade-auth-default-error-key="auth.mfa.errors.invalid">
    <?= $helpers->csrfField() ?>
    <div class="lm-message lm-message--error lm-auth-error" role="alert" data-lemonade-auth-error<?= $errorKey === null ? ' hidden' : '' ?>>
        <i class="<?= e(AdminIcon::XCircle->cssClass()) ?> lm-message-icon" aria-hidden="true"></i>
        <span class="lm-message-copy" data-lemonade-auth-error-copy<?= $errorKey === null || $errorMessage !== null ? '' : ' data-lemonade-i18n="' . e($errorKey) . '"' ?>><?= $errorKey === null ? '' : e($errorMessage ?? $helpers->lang($errorKey)) ?></span>
        <button class="lm-message-close" type="button" data-lemonade-message-close aria-label="<?= e($helpers->lang('auth.common.close')) ?>" title="<?= e($helpers->lang('auth.common.close')) ?>" data-lemonade-i18n-aria-label="auth.common.close" data-lemonade-i18n-title="auth.common.close"><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i></button>
    </div>
    <div class="lm-auth-field"><label class="visually-hidden" for="mfa-code" data-lemonade-i18n="auth.mfa.code"><?= e($helpers->lang('auth.mfa.code')) ?></label><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::ShieldLock->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $errorKey !== null ? ' is-invalid' : '' ?>" id="mfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="<?= e($helpers->lang('auth.mfa.code_placeholder')) ?>" data-lemonade-i18n-placeholder="auth.mfa.code_placeholder" required autofocus></div>
    <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.mfa.verify"><?= e($helpers->lang('auth.mfa.verify')) ?></button>
</form>
