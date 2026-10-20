<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var string $returnUrl */
/** @var bool $oidc */
/** @var int $retryAfterSeconds */

$throttled = !$oidc && $retryAfterSeconds > 0;
$throttleKey = $retryAfterSeconds < 60 ? 'auth.errors.throttled_seconds' : 'auth.errors.throttled_minutes';
$throttleParameters = $retryAfterSeconds < 60
    ? ['seconds' => max(1, $retryAfterSeconds)]
    : ['minutes' => (int) ceil($retryAfterSeconds / 60)];
?>
<h1 class="lm-auth-title" id="auth-title" data-lemonade-i18n="auth.session_lock.title"><?= e($helpers->lang('auth.session_lock.title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="<?= e($oidc ? 'auth.session_lock.oidc_description' : 'auth.session_lock.description') ?>"><?= e($helpers->lang($oidc ? 'auth.session_lock.oidc_description' : 'auth.session_lock.description')) ?></p>
<section data-lemonade-session-lock-screen data-lemonade-session-unlock-url="<?= e($helpers->url('admin.session.unlock')) ?>" data-lemonade-session-oidc-unlock-url="<?= e($helpers->url('admin.session.oidc-unlock')) ?>" data-lemonade-session-status-url="<?= e($helpers->url('admin.session.status')) ?>" data-lemonade-session-return-url="<?= e($returnUrl) ?>" data-lemonade-session-oidc="<?= $oidc ? 'true' : 'false' ?>" data-lemonade-session-retry-after="<?= e((string) $retryAfterSeconds) ?>">
    <div class="lm-message lm-message--error lm-auth-error" role="alert" data-lemonade-session-lock-error<?= $throttled ? '' : ' hidden' ?>><i class="<?= e(AdminIcon::XCircle->cssClass()) ?> lm-message-icon" aria-hidden="true"></i><span class="lm-message-copy" data-lemonade-session-lock-error-copy><?= $throttled ? e($helpers->lang($throttleKey, $throttleParameters)) : '' ?></span></div>
    <?php if ($oidc): ?>
        <button class="btn lm-auth-submit" type="button" data-lemonade-session-oidc-unlock data-lemonade-i18n="auth.session_lock.oidc"><?= e($helpers->lang('auth.session_lock.oidc')) ?></button>
    <?php else: ?>
        <form class="lm-auth-form" data-lemonade-session-local-unlock>
            <?= $helpers->csrfField() ?>
            <div class="lm-auth-field"><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control" name="password" type="password" autocomplete="current-password" placeholder="<?= e($helpers->lang('auth.session_lock.password')) ?>" required></div>
            <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.session_lock.unlock"<?= $throttled ? ' disabled aria-disabled="true"' : '' ?>><?= e($helpers->lang('auth.session_lock.unlock')) ?></button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e($helpers->url('admin.logout')) ?>" class="mt-3"><?= $helpers->csrfField() ?><button class="btn lm-auth-submit lm-auth-submit--secondary" type="submit"><i class="<?= e(AdminIcon::BoxArrowRight->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="auth.session_lock.logout"><?= e($helpers->lang('auth.session_lock.logout')) ?></span></button></form>
</section>
