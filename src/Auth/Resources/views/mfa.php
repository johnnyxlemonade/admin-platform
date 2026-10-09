<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var string|null $errorKey */
?>
<h1 class="lm-auth-title" data-lemonade-i18n="auth.mfa.title"><?= e($helpers->lang('auth.mfa.title')) ?></h1>
<p class="lm-auth-description" data-lemonade-i18n="auth.mfa.challenge_description"><?= e($helpers->lang('auth.mfa.challenge_description')) ?></p>
<?php if ($errorKey !== null): ?><div class="lm-message lm-message--error" role="alert"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i> <span data-lemonade-i18n="<?= e($errorKey) ?>"><?= e($helpers->lang($errorKey)) ?></span></div><?php endif; ?>
<form class="lm-auth-form" method="post" action="<?= e($helpers->url('admin.login.mfa.verify')) ?>">
    <?= $helpers->csrfField() ?>
    <div class="lm-auth-field"><label for="mfa-code" data-lemonade-i18n="auth.mfa.code"><?= e($helpers->lang('auth.mfa.code')) ?></label><input class="form-control" id="mfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required autofocus></div>
    <button class="btn lm-auth-submit" type="submit" data-lemonade-i18n="auth.mfa.verify"><?= e($helpers->lang('auth.mfa.verify')) ?></button>
</form>
