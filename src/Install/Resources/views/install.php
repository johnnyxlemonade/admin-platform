<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Install\InstallStepStatus;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var View $this */
/** @var bool $initial */
/** @var string $workflowEndpoint */
/** @var array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>} $preflight */
/** @var list<array{step:string,status:InstallStepStatus,messageKey:string}> $stepStates */
/** @var array<string,string> $errors */
/** @var string|null $generalError */
/** @var array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}|null $result */
/** @var string $email */
/** @var array<string,mixed> $installerTranslations */

$errorFor = static fn(string $field): string => isset($errors[$field]) ? (string) $errors[$field] : '';
$preflightReady = $preflight['ready'];
?>
<section class="lemonade-auth-content lemonade-install" aria-labelledby="install-title" data-lemonade-install data-install-initial="<?= $initial ? '1' : '0' ?>" data-install-preflight-ready="<?= $preflightReady ? '1' : '0' ?>" data-install-endpoint="<?= e($workflowEndpoint) ?>" data-install-login-url="<?= e($helpers->url('admin.login')) ?>" data-install-generic-error="<?= e($helpers->lang('install.install.result.failure')) ?>">
    <script type="application/json" data-install-translations data-lemonade-i18n-embedded><?= json_encode($installerTranslations, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <header class="lemonade-install-intro">
        <span class="lemonade-install-kicker" data-lemonade-i18n="install.installSteps.environment"><i class="<?= e(AdminIcon::ShieldCheck->cssClass()) ?>" aria-hidden="true"></i><?= e($helpers->lang('install.installSteps.environment')) ?></span>
        <h1 id="install-title" class="lm-auth-title" data-lemonade-i18n="install.install.title"><?= e($helpers->lang('install.install.title')) ?></h1>
        <p class="lm-auth-description" data-lemonade-i18n="install.install.description"><?= e($helpers->lang('install.install.description')) ?></p>
    </header>

    <?= $this->partial('admin-install::partials.workflow', [
        'showEnvironment' => true,
        'headingPrefix' => 'install',
        'preflight' => $preflight,
        'stepStates' => $stepStates,
        'generalError' => $generalError,
        'result' => $result,
    ]) ?>

    <?php if (!$preflightReady): ?><div class="lemonade-install-preflight-blocked alert alert-warning" role="alert"><i class="<?= e(AdminIcon::ExclamationTriangle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($helpers->lang('install.installErrors.preflight')) ?></span></div><?php endif; ?>

    <?php if ($preflightReady && ($result === null || !$result['success'])): ?>
        <form class="lemonade-install-form lm-auth-form" method="post" action="<?= e($workflowEndpoint) ?>" data-install-form data-install-progressive>
            <?= $helpers->csrfField() ?>
            <div class="lm-auth-field lemonade-install-field">
                <label class="form-label" for="install-key" data-lemonade-i18n="install.install.fields.install_key"><?= e($helpers->lang('install.install.fields.install_key')) ?></label>
                <div class="lemonade-install-control"><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control" id="install-key" name="install_key" type="password" autocomplete="off" aria-describedby="install-key-help" data-lemonade-password-input required><button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('install.password.show')) ?>" data-lemonade-password-show-key="install.password.show" data-lemonade-password-hide-key="install.password.hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button></div>
                <span class="form-text" id="install-key-help" data-lemonade-i18n="install.installPresentation.help.install_key"><?= e($helpers->lang('install.installPresentation.help.install_key')) ?></span>
            </div>
            <div class="lm-auth-field lemonade-install-field<?= $errorFor('email') === '' ? '' : ' has-error' ?>">
                <label class="form-label" for="install-email" data-lemonade-i18n="install.install.fields.email"><?= e($helpers->lang('install.install.fields.email')) ?></label>
                <div class="lemonade-install-control"><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Envelope->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $errorFor('email') === '' ? '' : ' is-invalid' ?>" id="install-email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" aria-describedby="install-email-help<?= $errorFor('email') === '' ? '' : ' install-email-error' ?>" required></div>
                <span class="form-text" id="install-email-help" data-lemonade-i18n="install.installPresentation.help.email"><?= e($helpers->lang('install.installPresentation.help.email')) ?></span>
                <?php if ($errorFor('email') !== ''): ?><span class="invalid-feedback d-block" id="install-email-error"><?= e($errorFor('email')) ?></span><?php endif; ?>
            </div>
            <div class="lm-auth-field lemonade-install-field<?= $errorFor('password') === '' ? '' : ' has-error' ?>">
                <label class="form-label" for="install-password" data-lemonade-i18n="install.install.fields.password"><?= e($helpers->lang('install.install.fields.password')) ?></label>
                <div class="lemonade-install-control"><span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i></span><input class="form-control<?= $errorFor('password') === '' ? '' : ' is-invalid' ?>" id="install-password" name="password" type="password" autocomplete="new-password" aria-describedby="install-password-help<?= $errorFor('password') === '' ? '' : ' install-password-error' ?>" data-lemonade-password-input required><button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('install.password.show')) ?>" data-lemonade-password-show-key="install.password.show" data-lemonade-password-hide-key="install.password.hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button></div>
                <span class="form-text" id="install-password-help" data-lemonade-i18n="install.installPresentation.help.password"><?= e($helpers->lang('install.installPresentation.help.password')) ?></span>
                <?php if ($errorFor('password') !== ''): ?><span class="invalid-feedback d-block" id="install-password-error"><?= e($errorFor('password')) ?></span><?php endif; ?>
            </div>
            <?php if ($errors !== [] && $errorFor('email') === '' && $errorFor('password') === ''): ?><div class="lemonade-install-form-errors" role="alert"><?php foreach ($errors as $error): ?><?= e($error) ?><br><?php endforeach; ?></div><?php endif; ?>
            <button class="btn btn-primary lm-auth-submit lemonade-install-submit" type="submit" data-install-submit><span data-install-submit-label data-lemonade-i18n="install.install.actions.install"><?= e($helpers->lang('install.install.actions.install')) ?></span><span class="spinner-border spinner-border-sm" data-install-submit-spinner role="status" aria-hidden="true" hidden></span></button>
        </form>
    <?php endif; ?>
</section>
