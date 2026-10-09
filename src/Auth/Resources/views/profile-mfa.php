<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var bool $active */
/** @var bool $recent */
/** @var bool $canDisable */
?>
<h1 class="lm-auth-title" data-lemonade-i18n="auth.mfa.profile_title"><?= e($helpers->lang('auth.mfa.profile_title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="<?= $active ? 'auth.mfa.active' : 'auth.mfa.inactive' ?>"><?= e($helpers->lang($active ? 'auth.mfa.active' : 'auth.mfa.inactive')) ?></p>
<?php if (!$recent): ?>
<form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.profile.mfa.reauth')) ?>">
    <?= $helpers->csrfField() ?>
    <div class="lm-auth-field"><label for="mfa-password" data-lemonade-i18n="auth.login.password"><?= e($helpers->lang('auth.login.password')) ?></label><input class="form-control" id="mfa-password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.mfa.reauthenticate"><?= e($helpers->lang('auth.mfa.reauthenticate')) ?></button>
</form>
<?php else: ?>
<form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.profile.mfa.enroll.start')) ?>"><?= $helpers->csrfField() ?><button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="<?= $active ? 'auth.mfa.reenroll' : 'auth.mfa.enroll_start' ?>"><?= e($helpers->lang($active ? 'auth.mfa.reenroll' : 'auth.mfa.enroll_start')) ?></button></form>
<?php if ($active && $canDisable): ?><form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.profile.mfa.disable')) ?>"><?= $helpers->csrfField() ?><button class="btn btn-outline-danger" type="submit" data-lemonade-i18n="auth.mfa.disable"><?= e($helpers->lang('auth.mfa.disable')) ?></button></form><?php endif; ?>
<?php endif; ?>
