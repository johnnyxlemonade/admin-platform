<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var string $email */
/** @var ViewHelpers $helpers */
/** @var string $locale */
/** @var string|null $messageKey */
/** @var string|null $messageType */
?>
<h1 class="lm-auth-title" id="auth-title" data-lemonade-i18n="auth.forgot.title"><?= e($helpers->lang('auth.forgot.title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="auth.forgot.description"><?= e($helpers->lang('auth.forgot.description')) ?></p>
<form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.login.forgot.submit')) ?>">
    <?= $helpers->csrfField() ?>
    <?php if ($messageKey !== null && $messageType !== null): ?>
        <div class="lm-message lm-message--<?= e($messageType) ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
            <i class="<?= e(($messageType === 'error' ? AdminIcon::XCircle : AdminIcon::InfoCircle)->cssClass()) ?> lm-message-icon" aria-hidden="true"></i>
            <span class="lm-message-copy"><?= e($helpers->lang($messageKey)) ?></span>
            <button class="lm-message-close" type="button" data-lemonade-message-close aria-label="<?= e($helpers->lang('auth.common.close')) ?>" title="<?= e($helpers->lang('auth.common.close')) ?>" data-lemonade-i18n-aria-label="auth.common.close" data-lemonade-i18n-title="auth.common.close"><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i></button>
        </div>
    <?php endif; ?>
    <div class="lm-auth-field"><label class="visually-hidden" for="forgot-email" data-lemonade-i18n="auth.login.email"><?= e($helpers->lang('auth.login.email')) ?></label><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Envelope->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $messageType === 'error' ? ' is-invalid' : '' ?>" id="forgot-email" name="email" type="email" autocomplete="email" placeholder="<?= e($helpers->lang('auth.login.email')) ?>" data-lemonade-i18n-placeholder="auth.login.email" value="<?= e($email) ?>" required></div>
    <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.forgot.submit"><?= e($helpers->lang('auth.forgot.submit')) ?></button>
</form>
<a class="lm-auth-link" href="<?= e($helpers->url('admin.login')) ?>"><i class="<?= e(AdminIcon::ArrowLeft->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="auth.forgot.back"><?= e($helpers->lang('auth.forgot.back')) ?></span></a>
