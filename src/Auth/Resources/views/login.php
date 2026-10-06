<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var string $email */
/** @var string|null $errorKey */
/** @var string $locale */
/** @var bool $oidcEnabled */
/** @var AdminBranding $branding */

$oidcProviderDisplayName = $branding->oidcProviderDisplayName;
$descriptionKey = $oidcEnabled && $oidcProviderDisplayName !== null
    ? 'auth.login.description.local_or_provider'
    : ($oidcEnabled
        ? 'auth.login.description.local_or_sso'
        : 'auth.login.description.local_only');
?>
<h1 class="lm-auth-title" id="auth-title" data-lemonade-i18n="auth.login.title"><?= e($helpers->lang('auth.login.title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="<?= e($descriptionKey) ?>"<?= $oidcProviderDisplayName === null ? '' : ' data-lemonade-i18n-param-provider="' . e($oidcProviderDisplayName) . '"' ?>><?= e($helpers->lang($descriptionKey, ['provider' => $oidcProviderDisplayName])) ?></p>
<?php if ($oidcEnabled): ?>
    <div class="lm-auth-oidc">
        <a class="btn lm-auth-keycloak lm-auth-keycloak--primary" href="<?= e($helpers->url('admin.login.keycloak')) ?>">
            <i class="<?= e(AdminIcon::ShieldCheck->cssClass()) ?>" aria-hidden="true"></i>
            <?php if ($oidcProviderDisplayName !== null): ?><span data-lemonade-i18n="auth.login.sso_provider" data-lemonade-i18n-param-provider="<?= e($oidcProviderDisplayName) ?>"><?= e($helpers->lang('auth.login.sso_provider', ['provider' => $oidcProviderDisplayName])) ?></span><?php else: ?><span data-lemonade-i18n="auth.login.sso"><?= e($helpers->lang('auth.login.sso')) ?></span><?php endif; ?>
        </a>
        <div class="lm-auth-divider"><span data-lemonade-i18n="auth.login.or"><?= e($helpers->lang('auth.login.or')) ?></span></div>
    </div>
<?php endif; ?>
<form class="lm-auth-form<?= $oidcEnabled ? ' lm-auth-form--oidc-fallback' : '' ?>" method="post" action="<?= e($helpers->url('admin.login.submit')) ?>" autocomplete="off" data-lemonade-auth-login data-lemonade-auth-default-error="<?= e($helpers->lang('auth.errors.invalid')) ?>">
    <?= $helpers->csrfField() ?>
    <div class="lm-message lm-message--error" role="alert" data-lemonade-auth-error<?= $errorKey === null ? ' hidden' : '' ?>>
        <i class="<?= e(AdminIcon::XCircle->cssClass()) ?> lm-message-icon" aria-hidden="true"></i>
        <span class="lm-message-copy" data-lemonade-auth-error-copy<?= $errorKey === null ? '' : ' data-lemonade-i18n="' . e($errorKey) . '"' ?>><?= $errorKey === null ? '' : e($helpers->lang($errorKey)) ?></span>
        <button class="lm-message-close" type="button" data-lemonade-message-close aria-label="<?= e($helpers->lang('auth.common.close')) ?>" title="<?= e($helpers->lang('auth.common.close')) ?>" data-lemonade-i18n-aria-label="auth.common.close" data-lemonade-i18n-title="auth.common.close"><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i></button>
    </div>
    <div class="lm-auth-credentials">
        <div class="lm-auth-field"><label class="visually-hidden" for="login-email" data-lemonade-i18n="auth.login.email"><?= e($helpers->lang('auth.login.email')) ?></label><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Envelope->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $errorKey !== null ? ' is-invalid' : '' ?>" id="login-email" name="email" type="email" autocomplete="off" placeholder="<?= e($helpers->lang('auth.login.email')) ?>" data-lemonade-i18n-placeholder="auth.login.email" value="<?= e($email) ?>" required></div>
        <div class="lm-auth-field"><label class="visually-hidden" for="login-password" data-lemonade-i18n="auth.login.password"><?= e($helpers->lang('auth.login.password')) ?></label><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $errorKey !== null ? ' is-invalid' : '' ?>" id="login-password" name="password" type="password" autocomplete="new-password" placeholder="<?= e($helpers->lang('auth.login.password')) ?>" data-lemonade-password-input data-lemonade-i18n-placeholder="auth.login.password" required><button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('auth.password.show')) ?>" data-lemonade-i18n-aria-label="auth.password.show"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button></div>
    </div>
    <div class="lm-auth-options"><div class="form-check"><input class="form-check-input" id="login-remember" name="remember" type="checkbox"><label class="form-check-label" for="login-remember" data-lemonade-i18n="auth.login.remember"><?= e($helpers->lang('auth.login.remember')) ?></label></div><a href="<?= e($helpers->url('admin.login.forgot')) ?>" data-lemonade-i18n="auth.login.forgot"><?= e($helpers->lang('auth.login.forgot')) ?></a></div>
    <button class="btn lm-auth-submit<?= $oidcEnabled ? ' lm-auth-submit--secondary' : '' ?>" type="submit" data-lemonade-i18n="auth.login.submit"><?= e($helpers->lang('auth.login.submit')) ?></button>
</form>
<?php if ($branding->homeUrl !== null): ?><a class="lm-auth-link" href="<?= e($branding->homeUrl) ?>"><i class="<?= e(AdminIcon::ArrowLeft->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="auth.login.back"><?= e($helpers->lang('auth.login.back')) ?></span></a><?php endif; ?>
