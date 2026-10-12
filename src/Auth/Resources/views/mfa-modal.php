<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var bool $active */
/** @var bool $recent */
/** @var bool $canDisable */
/** @var string|null $errorKey */
/** @var string|null $noticeKey */
/** @var string|null $secret */
/** @var string|null $provisioningUri */
$completed = $noticeKey === 'auth.mfa.enrolled';
$enrolling = $secret !== null;
$titleKey = $completed ? 'auth.mfa.success_title' : ($enrolling && $active ? 'auth.mfa.reenroll_title' : 'auth.mfa.profile_title');
?>
<header class="lm-modal-header lm-mfa-modal-header">
    <div class="lm-mfa-modal-title"><span class="lm-mfa-modal-title-icon"><i class="<?= e(($completed ? AdminIcon::CheckCircle : AdminIcon::ShieldLock)->cssClass()) ?>" aria-hidden="true"></i></span><h2 data-lemonade-modal-title data-lemonade-i18n="<?= e($titleKey) ?>"><?= e($helpers->lang($titleKey)) ?></h2></div>
</header>

<?php if ($completed): ?>
    <section class="lm-modal-body lm-mfa-completed"><span class="lm-mfa-completed-icon"><i class="<?= e(AdminIcon::CheckCircle->cssClass()) ?>" aria-hidden="true"></i></span><h3 data-lemonade-i18n="auth.mfa.success_title"><?= e($helpers->lang('auth.mfa.success_title')) ?></h3><p data-lemonade-i18n="auth.mfa.success_description"><?= e($helpers->lang('auth.mfa.success_description')) ?></p></section>
    <footer class="lm-modal-footer"><button class="btn btn-primary lm-button-primary" type="button" data-lemonade-modal-close="success" data-lemonade-i18n="auth.common.close"><?= e($helpers->lang('auth.common.close')) ?></button></footer>
<?php elseif (!$recent): ?>
    <form class="lm-mfa-enrollment-form" method="post" action="<?= e($helpers->url('admin.mfa.modal.reauth')) ?>" data-lemonade-modal-form>
        <?= $helpers->csrfField() ?>
        <section class="lm-modal-body lm-mfa-reauth-body"><p class="lm-mfa-enrollment-intro" data-lemonade-i18n="auth.mfa.reauth_description"><?= e($helpers->lang('auth.mfa.reauth_description')) ?></p><div class="lm-auth-field lm-mfa-code-field"><label for="mfa-password" data-lemonade-i18n="auth.login.password"><?= e($helpers->lang('auth.login.password')) ?></label><input class="form-control" id="mfa-password" name="password" type="password" autocomplete="current-password" required autofocus></div><?php if ($errorKey !== null): ?><p class="lm-mfa-inline-error" role="alert" data-lemonade-i18n="<?= e($errorKey) ?>"><?= e($helpers->lang($errorKey)) ?></p><?php endif; ?><div class="lm-mfa-info"><i class="<?= e(AdminIcon::InfoCircle->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="auth.mfa.reauth_help"><?= e($helpers->lang('auth.mfa.reauth_help')) ?></span></div></section>
        <footer class="lm-modal-footer"><button class="btn btn-light" type="button" data-lemonade-modal-close="cancel" data-lemonade-i18n="admin.common.cancel"><?= e($helpers->lang('admin.common.cancel')) ?></button><button class="btn btn-primary lm-button-primary" type="submit" data-lemonade-i18n="auth.mfa.reauthenticate"><?= e($helpers->lang('auth.mfa.reauthenticate')) ?></button></footer>
    </form>
<?php elseif ($enrolling): ?>
    <form class="lm-mfa-enrollment-form" method="post" action="<?= e($helpers->url('admin.mfa.modal.enroll.confirm')) ?>" data-lemonade-modal-form>
        <?= $helpers->csrfField() ?>
        <section class="lm-modal-body lm-mfa-enrollment-body"><p class="lm-mfa-enrollment-intro" data-lemonade-i18n="auth.mfa.enroll_scan"><?= e($helpers->lang('auth.mfa.enroll_scan')) ?></p><div class="lm-mfa-enrollment-provisioning"><div class="lm-mfa-qr-frame"><canvas class="lm-auth-mfa-qr" data-lemonade-mfa-qr data-provisioning-uri="<?= e($provisioningUri ?? '') ?>" aria-label="<?= e($helpers->lang('auth.mfa.enroll_scan')) ?>" data-lemonade-i18n-aria-label="auth.mfa.enroll_scan"></canvas></div><aside class="lm-mfa-enrollment-help"><div class="lm-mfa-enrollment-help-heading"><i class="<?= e(AdminIcon::InfoCircle->cssClass()) ?>" aria-hidden="true"></i><strong data-lemonade-i18n="auth.mfa.app_title"><?= e($helpers->lang('auth.mfa.app_title')) ?></strong></div><p data-lemonade-i18n="auth.mfa.app_description"><?= e($helpers->lang('auth.mfa.app_description')) ?></p><hr><strong data-lemonade-i18n="auth.mfa.manual_title"><?= e($helpers->lang('auth.mfa.manual_title')) ?></strong><p data-lemonade-i18n="auth.mfa.manual_description"><?= e($helpers->lang('auth.mfa.manual_description')) ?></p></aside></div><div class="lm-mfa-manual-key"><label for="mfa-manual-key" data-lemonade-i18n="auth.mfa.manual_key"><?= e($helpers->lang('auth.mfa.manual_key')) ?></label><div class="lm-mfa-manual-key-control"><input class="form-control" id="mfa-manual-key" type="text" value="<?= e($secret) ?>" readonly autocomplete="off"><button class="btn btn-light btn-sm" type="button" data-lemonade-mfa-copy data-lemonade-mfa-copy-target="mfa-manual-key" data-lemonade-i18n="auth.mfa.copy"><?= e($helpers->lang('auth.mfa.copy')) ?></button></div></div><div class="lm-auth-field lm-mfa-code-field"><label for="mfa-code" data-lemonade-i18n="auth.mfa.code"><?= e($helpers->lang('auth.mfa.code')) ?></label><input class="form-control" id="mfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" placeholder="<?= e($helpers->lang('auth.mfa.code_placeholder')) ?>" data-lemonade-i18n-placeholder="auth.mfa.code_placeholder" required autofocus></div><?php if ($errorKey !== null): ?><p class="lm-mfa-inline-error" role="alert" data-lemonade-i18n="<?= e($errorKey) ?>"><?= e($helpers->lang($errorKey)) ?></p><?php endif; ?></section>
        <footer class="lm-modal-footer"><button class="btn btn-light" type="button" data-lemonade-modal-close="cancel" data-lemonade-i18n="admin.common.back"><?= e($helpers->lang('admin.common.back')) ?></button><button class="btn btn-primary lm-button-primary" type="submit" data-lemonade-i18n="auth.mfa.enroll_confirm"><?= e($helpers->lang('auth.mfa.enroll_confirm')) ?></button></footer>
    </form>
<?php elseif ($active): ?>
    <section class="lm-modal-body lm-mfa-active-body"><div class="lm-mfa-active-status"><i class="<?= e(AdminIcon::CheckCircle->cssClass()) ?>" aria-hidden="true"></i><div><strong data-lemonade-i18n="auth.mfa.active"><?= e($helpers->lang('auth.mfa.active')) ?></strong><span data-lemonade-i18n="auth.mfa.active_description"><?= e($helpers->lang('auth.mfa.active_description')) ?></span></div></div><form method="post" action="<?= e($helpers->url('admin.mfa.modal.enroll.start')) ?>" data-lemonade-modal-form><?= $helpers->csrfField() ?><button class="btn btn-outline-secondary w-100" type="submit" data-lemonade-i18n="auth.mfa.reenroll"><?= e($helpers->lang('auth.mfa.reenroll')) ?></button></form><?php if ($canDisable): ?><form class="mt-2" method="post" action="<?= e($helpers->url('admin.mfa.modal.disable')) ?>" data-lemonade-modal-form><?= $helpers->csrfField() ?><button class="btn btn-outline-danger w-100" type="submit" data-lemonade-i18n="auth.mfa.disable"><?= e($helpers->lang('auth.mfa.disable')) ?></button></form><?php endif; ?></section>
    <footer class="lm-modal-footer"><button class="btn btn-light" type="button" data-lemonade-modal-close="cancel" data-lemonade-i18n="auth.common.close"><?= e($helpers->lang('auth.common.close')) ?></button></footer>
<?php else: ?>
    <section class="lm-modal-body"><p data-lemonade-i18n="auth.mfa.inactive"><?= e($helpers->lang('auth.mfa.inactive')) ?></p><form method="post" action="<?= e($helpers->url('admin.mfa.modal.enroll.start')) ?>" data-lemonade-modal-form><?= $helpers->csrfField() ?><button class="btn btn-primary lm-button-primary" type="submit" data-lemonade-i18n="auth.mfa.enroll_start"><?= e($helpers->lang('auth.mfa.enroll_start')) ?></button></form></section>
<?php endif; ?>
