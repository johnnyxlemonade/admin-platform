<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var bool $profile */
/** @var string|null $secret */
/** @var string|null $provisioningUri */
/** @var string|null $errorKey */

$startRoute = $profile ? 'admin.profile.mfa.enroll.start' : 'admin.login.mfa.enroll.start';
$confirmRoute = $profile ? 'admin.profile.mfa.enroll.confirm' : 'admin.login.mfa.enroll.confirm';
?>
<h1 class="lm-auth-title" data-lemonade-i18n="auth.mfa.enroll_title"><?= e($helpers->lang('auth.mfa.enroll_title')) ?></h1>
<?php if ($secret === null): ?>
    <p class="lm-auth-description" data-lemonade-i18n="auth.mfa.enroll_description"><?= e($helpers->lang('auth.mfa.enroll_description')) ?></p>
    <form class="lm-auth-form" method="post" action="<?= e($helpers->url($startRoute)) ?>"><?= $helpers->csrfField() ?><button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.mfa.enroll_start"><?= e($helpers->lang('auth.mfa.enroll_start')) ?></button></form>
<?php else: ?>
    <p class="lm-auth-description" data-lemonade-i18n="auth.mfa.enroll_scan"><?= e($helpers->lang('auth.mfa.enroll_scan')) ?></p>
    <canvas class="lm-auth-mfa-qr" data-lemonade-mfa-qr data-provisioning-uri="<?= e($provisioningUri ?? '') ?>" aria-label="<?= e($helpers->lang('auth.mfa.enroll_scan')) ?>" data-lemonade-i18n-aria-label="auth.mfa.enroll_scan"></canvas>
    <p><code><?= e($secret) ?></code></p>
    <?php if ($errorKey !== null): ?><div class="lm-message lm-message--error" role="alert"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i> <span data-lemonade-i18n="<?= e($errorKey) ?>"><?= e($helpers->lang($errorKey)) ?></span></div><?php endif; ?>
    <form class="lm-auth-form" method="post" action="<?= e($helpers->url($confirmRoute)) ?>">
        <?= $helpers->csrfField() ?>
        <div class="lm-auth-field"><label for="mfa-code" data-lemonade-i18n="auth.mfa.code"><?= e($helpers->lang('auth.mfa.code')) ?></label><input class="form-control" id="mfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required autofocus></div>
        <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.mfa.enroll_confirm"><?= e($helpers->lang('auth.mfa.enroll_confirm')) ?></button>
    </form>
<?php endif; ?>
