<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Install\InstallStepStatus;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var View $this */
/** @var bool $initial */
/** @var bool $catalogExists */
/** @var array{databaseConnected:bool,databaseSchema:bool,rootAccount:bool} $state */
/** @var array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>} $preflight */
/** @var list<array{step:string,status:InstallStepStatus,messageKey:string}> $stepStates */
/** @var array<string,string> $errors */
/** @var string|null $generalError */
/** @var array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}|null $result */
/** @var string $email */
/** @var array<string,mixed> $installerTranslations */

$errorFor = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? (string) $errors[$field] : '';
};

$iconForStatus = static function (string $status): string {
    return match ($status) {
        'success' => AdminIcon::CheckCircle->cssClass(),
        'error' => AdminIcon::XCircle->cssClass(),
        default => AdminIcon::Circle->cssClass(),
    };
};

$passedChecks = count(array_filter($preflight['checks'], static fn(array $check): bool => $check['ok']));
$totalChecks = count($preflight['checks']);
$preflightReady = $preflight['ready'];
$environmentStatus = $preflight['ready'] ? 'success' : 'error';
?>
<section class="lemonade-auth-content lemonade-install" aria-labelledby="install-title" data-lemonade-install data-install-initial="<?= $initial ? '1' : '0' ?>" data-install-catalog="<?= $catalogExists ? '1' : '0' ?>" data-install-preflight-ready="<?= $preflightReady ? '1' : '0' ?>" data-install-endpoint="<?= e($helpers->url('admin.install.run')) ?>" data-install-login-url="<?= e($helpers->url('admin.login')) ?>" data-install-generic-error="<?= e($helpers->lang('install.install.result.failure')) ?>">
    <script type="application/json" data-install-translations data-lemonade-i18n-embedded><?= json_encode($installerTranslations, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <header class="lemonade-install-intro">
        <span class="lemonade-install-kicker" data-lemonade-i18n="install.installSteps.environment"><i class="<?= e(AdminIcon::ShieldCheck->cssClass()) ?>" aria-hidden="true"></i><?= e($helpers->lang('install.installSteps.environment')) ?></span>
        <h1 id="install-title" class="lm-auth-title" data-lemonade-i18n="install.install.<?= $initial ? 'title' : 'update_title' ?>"><?= e($helpers->lang($initial ? 'install.install.title' : 'install.install.update_title')) ?></h1>
        <p class="lm-auth-description" data-lemonade-i18n="install.install.<?= $initial ? 'description' : 'update_description' ?>"><?= e($helpers->lang($initial ? 'install.install.description' : 'install.install.update_description')) ?></p>
    </header>

    <?php if ($generalError !== null): ?><div class="lemonade-install-feedback alert alert-danger" role="alert"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><span><?= e((string) $generalError) ?></span></div><?php endif; ?>
    <?php if ($result !== null): ?><div class="lemonade-install-feedback alert <?= $result['success'] ? 'alert-success' : 'alert-danger' ?>" role="status"><i class="<?= e($result['success'] ? AdminIcon::CheckCircle->cssClass() : AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($helpers->lang($result['success'] ? 'install.install.result.success' : 'install.install.result.failure')) ?></span></div><?php endif; ?>

    <section class="lemonade-install-section" aria-labelledby="install-preflight-title">
        <div class="lemonade-install-section-heading">
            <div>
                <span class="lemonade-install-section-kicker" data-lemonade-i18n="install.install.preflight.title"><?= e($helpers->lang('install.install.preflight.title')) ?></span>
                <h2 id="install-preflight-title" data-lemonade-i18n="install.install.preflight.title"><?= e($helpers->lang('install.install.preflight.title')) ?></h2>
            </div>
            <span class="lemonade-install-summary" role="status" data-lemonade-i18n="install.installPresentation.preflight_summary" data-lemonade-i18n-param-passed="<?= e((string) $passedChecks) ?>" data-lemonade-i18n-param-total="<?= e((string) $totalChecks) ?>"><?= e($helpers->lang('install.installPresentation.preflight_summary', ['passed' => $passedChecks, 'total' => $totalChecks])) ?></span>
        </div>
        <div class="row g-2 lemonade-install-checks">
            <?php foreach ($preflight['checks'] as $check): ?>
                <?php $checkStatus = $check['status']; ?>
                <?php $checkIcon = match ($checkStatus) {
                    'success' => AdminIcon::CheckCircle->cssClass(), 'error' => AdminIcon::XCircle->cssClass(), default => AdminIcon::Circle->cssClass(),
                }; ?>
                <div class="col-12 col-md-6">
                    <div class="lemonade-install-check lemonade-install-check--<?= e($checkStatus) ?>">
                        <i class="lemonade-install-check-icon <?= e($checkIcon) ?>" aria-hidden="true"></i>
                        <div class="lemonade-install-check-copy">
                            <span class="lemonade-install-check-label" data-lemonade-i18n="install.install.checks.<?= e($check['key']) ?>"><?= e($helpers->lang('install.install.checks.' . $check['key'])) ?></span>
                            <span class="lemonade-install-check-status" data-lemonade-i18n="install.install.status.<?= e($checkStatus) ?>"><?= e($helpers->lang('install.install.status.' . $checkStatus)) ?></span>
                            <?php if ($checkStatus !== 'success' && $check['detail'] !== ''): ?><span class="lemonade-install-check-detail" role="note"><?= e($check['detail']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="lemonade-install-section lemonade-install-stepper" aria-labelledby="install-steps-title">
        <div class="lemonade-install-section-heading lemonade-install-section-heading--steps">
            <div>
                <span class="lemonade-install-section-kicker" data-lemonade-i18n="install.installPresentation.progress"><?= e($helpers->lang('install.installPresentation.progress')) ?></span>
                <h2 id="install-steps-title" data-lemonade-i18n="install.install.steps.title"><?= e($helpers->lang('install.install.steps.title')) ?></h2>
            </div>
            <span class="lemonade-install-summary" data-install-progress-label data-install-progress-template="<?= e($helpers->lang('install.installPresentation.progress_completed')) ?>"><?= e($helpers->lang('install.installSteps.pending')) ?></span>
        </div>
        <div class="progress lemonade-install-progress" role="progressbar" aria-label="<?= e($helpers->lang('install.installPresentation.progress')) ?>" data-lemonade-i18n-aria-label="install.installPresentation.progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-install-progress>
            <div class="progress-bar" data-install-progress-bar></div>
        </div>
        <ol class="lemonade-install-steps" data-install-steps aria-live="polite">
            <li class="lemonade-install-step" data-install-step="environment" data-install-status="<?= e($environmentStatus) ?>" data-install-status-pending="<?= e($helpers->lang('install.installSteps.pending')) ?>" data-install-status-running="<?= e($helpers->lang('install.installSteps.running')) ?>" data-install-status-success="<?= e($helpers->lang('install.installSteps.success')) ?>" data-install-status-error="<?= e($helpers->lang('install.installSteps.error')) ?>" data-install-status-skipped="<?= e($helpers->lang('install.installSteps.skipped')) ?>">
                <span class="lemonade-install-step-rail"><span data-install-step-icon class="lemonade-install-step-icon <?= e($iconForStatus($environmentStatus)) ?>" aria-hidden="true"></span><span class="lemonade-install-step-line" aria-hidden="true"></span></span>
                <span class="lemonade-install-step-copy"><span data-install-step-label class="lemonade-install-step-label" data-lemonade-i18n="install.installSteps.environment"><?= e($helpers->lang('install.installSteps.environment')) ?></span><span data-install-step-message class="lemonade-install-step-message" role="status"></span></span>
                <strong data-install-step-status class="badge lemonade-install-badge lemonade-install-badge--<?= e($environmentStatus) ?>" data-lemonade-i18n="install.installSteps.<?= e($environmentStatus) ?>"><?= e($helpers->lang('install.installSteps.' . $environmentStatus)) ?></strong>
            </li>
            <?php foreach ($stepStates as $step): ?>
                <?php $stepStatus = $step['status']->value; ?>
                <li class="lemonade-install-step" data-install-step="<?= e($step['step']) ?>" data-install-status="<?= e($stepStatus) ?>" data-install-status-pending="<?= e($helpers->lang('install.installSteps.pending')) ?>" data-install-status-running="<?= e($helpers->lang('install.installSteps.running')) ?>" data-install-status-success="<?= e($helpers->lang('install.installSteps.success')) ?>" data-install-status-error="<?= e($helpers->lang('install.installSteps.error')) ?>" data-install-status-skipped="<?= e($helpers->lang('install.installSteps.skipped')) ?>">
                    <span class="lemonade-install-step-rail"><span data-install-step-icon class="lemonade-install-step-icon <?= e($iconForStatus($stepStatus)) ?>" aria-hidden="true"></span><span class="lemonade-install-step-line" aria-hidden="true"></span></span>
                    <span class="lemonade-install-step-copy"><span data-install-step-label class="lemonade-install-step-label" data-lemonade-i18n="install.install.steps.<?= e($step['step']) ?>"><?= e($helpers->lang('install.install.steps.' . $step['step'])) ?></span><span data-install-step-message class="lemonade-install-step-message" role="status"></span></span>
                    <strong data-install-step-status class="badge lemonade-install-badge lemonade-install-badge--<?= e($stepStatus) ?>" data-lemonade-i18n="install.installSteps.<?= e($stepStatus) ?>"><?= e($helpers->lang('install.installSteps.' . $stepStatus)) ?></strong>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($result !== null): ?>
            <div class="lemonade-install-results" data-install-results aria-live="polite">
                <?php foreach ($result['steps'] as $resultStep): ?>
                    <?php if (!$resultStep['ok'] && $resultStep['error'] !== ''): ?><p data-install-result-step="<?= e($resultStep['key']) ?>"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><?= e($resultStep['error']) ?></p><?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$preflightReady): ?><div class="lemonade-install-preflight-blocked alert alert-warning" role="alert"><i class="<?= e(AdminIcon::ExclamationTriangle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($helpers->lang('install.installErrors.preflight')) ?></span></div><?php endif; ?>

    <?php if ($preflightReady && !$catalogExists): ?>
        <form class="lemonade-install-form lm-auth-form" method="post" action="<?= e($helpers->url('admin.install.run')) ?>" data-install-form data-install-preparation>
            <?= $helpers->csrfField() ?>
            <input type="hidden" name="prepare" value="1">
            <?php if ($initial): ?>
                <div class="lm-auth-field lemonade-install-field">
                    <label class="form-label" for="install-key" data-lemonade-i18n="install.install.fields.install_key"><?= e($helpers->lang('install.install.fields.install_key')) ?></label>
                    <div class="lemonade-install-control">
                        <span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i></span>
                        <input class="form-control" id="install-key" name="install_key" type="password" autocomplete="off" aria-describedby="install-key-help" data-lemonade-password-input required>
                        <button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('install.password.show')) ?>" data-lemonade-password-show-key="install.password.show" data-lemonade-password-hide-key="install.password.hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button>
                    </div>
                    <span class="form-text" id="install-key-help" data-lemonade-i18n="install.installPresentation.help.install_key"><?= e($helpers->lang('install.installPresentation.help.install_key')) ?></span>
                </div>
            <?php endif; ?>
            <button class="btn btn-primary lm-auth-submit lemonade-install-submit" type="submit" data-install-submit><span data-install-submit-label data-lemonade-i18n="install.installSteps.<?= $initial ? 'prepare' : 'refresh_catalog' ?>"><?= e($helpers->lang($initial ? 'install.installSteps.prepare' : 'install.installSteps.refresh_catalog')) ?></span><span class="spinner-border spinner-border-sm" data-install-submit-spinner role="status" aria-hidden="true" hidden></span></button>
        </form>
    <?php elseif ($preflight['ready'] && ($result === null || !$result['success'])): ?>
        <form class="lemonade-install-form lm-auth-form" method="post" action="<?= e($helpers->url('admin.install.run')) ?>" data-install-form data-install-progressive>
            <?= $helpers->csrfField() ?>
            <?php if ($initial): ?>
                <div class="lm-auth-field lemonade-install-field">
                    <label class="form-label" for="install-key" data-lemonade-i18n="install.install.fields.install_key"><?= e($helpers->lang('install.install.fields.install_key')) ?></label>
                    <div class="lemonade-install-control">
                        <span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i></span>
                        <input class="form-control" id="install-key" name="install_key" type="password" autocomplete="off" aria-describedby="install-key-help" data-lemonade-password-input required>
                        <button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('install.password.show')) ?>" data-lemonade-password-show-key="install.password.show" data-lemonade-password-hide-key="install.password.hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button>
                    </div>
                    <span class="form-text" id="install-key-help" data-lemonade-i18n="install.installPresentation.help.install_key"><?= e($helpers->lang('install.installPresentation.help.install_key')) ?></span>
                </div>
                <div class="lm-auth-field lemonade-install-field<?= $errorFor('email') === '' ? '' : ' has-error' ?>">
                    <label class="form-label" for="install-email" data-lemonade-i18n="install.install.fields.email"><?= e($helpers->lang('install.install.fields.email')) ?></label>
                    <div class="lemonade-install-control">
                        <span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Envelope->cssClass()) ?>" aria-hidden="true"></i></span>
                        <input class="form-control<?= $errorFor('email') === '' ? '' : ' is-invalid' ?>" id="install-email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" aria-describedby="install-email-help<?= $errorFor('email') === '' ? '' : ' install-email-error' ?>" required>
                    </div>
                    <span class="form-text" id="install-email-help" data-lemonade-i18n="install.installPresentation.help.email"><?= e($helpers->lang('install.installPresentation.help.email')) ?></span>
                    <?php if ($errorFor('email') !== ''): ?><span class="invalid-feedback d-block" id="install-email-error"><?= e($errorFor('email')) ?></span><?php endif; ?>
                </div>
                <div class="lm-auth-field lemonade-install-field<?= $errorFor('password') === '' ? '' : ' has-error' ?>">
                    <label class="form-label" for="install-password" data-lemonade-i18n="install.install.fields.password"><?= e($helpers->lang('install.install.fields.password')) ?></label>
                    <div class="lemonade-install-control">
                        <span class="lm-auth-field-icon"><i class="<?= e(AdminIcon::Lock->cssClass()) ?>" aria-hidden="true"></i></span>
                        <input class="form-control<?= $errorFor('password') === '' ? '' : ' is-invalid' ?>" id="install-password" name="password" type="password" autocomplete="new-password" aria-describedby="install-password-help<?= $errorFor('password') === '' ? '' : ' install-password-error' ?>" data-lemonade-password-input required>
                        <button class="lm-auth-password-toggle" type="button" data-lemonade-password-toggle aria-label="<?= e($helpers->lang('install.password.show')) ?>" data-lemonade-password-show-key="install.password.show" data-lemonade-password-hide-key="install.password.hide"><i class="<?= e(AdminIcon::Eye->cssClass()) ?>" aria-hidden="true"></i></button>
                    </div>
                    <span class="form-text" id="install-password-help" data-lemonade-i18n="install.installPresentation.help.password"><?= e($helpers->lang('install.installPresentation.help.password')) ?></span>
                    <?php if ($errorFor('password') !== ''): ?><span class="invalid-feedback d-block" id="install-password-error"><?= e($errorFor('password')) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($errors !== [] && $errorFor('email') === '' && $errorFor('password') === ''): ?><div class="lemonade-install-form-errors" role="alert"><?php foreach ($errors as $error): ?><?= e($error) ?><br><?php endforeach; ?></div><?php endif; ?>
            <button class="btn btn-primary lm-auth-submit lemonade-install-submit" type="submit" data-install-submit><span data-install-submit-label data-lemonade-i18n="install.install.actions.<?= $initial ? 'install' : 'update' ?>"><?= e($helpers->lang($initial ? 'install.install.actions.install' : 'install.install.actions.update')) ?></span><span class="spinner-border spinner-border-sm" data-install-submit-spinner role="status" aria-hidden="true" hidden></span></button>
        </form>
    <?php endif; ?>

    <?php if (!$initial && $catalogExists && $preflightReady): ?>
        <form class="lemonade-install-refresh" method="post" action="<?= e($helpers->url('admin.install.run')) ?>">
            <?= $helpers->csrfField() ?>
            <input type="hidden" name="prepare" value="1">
            <button class="btn btn-light" type="submit"><i class="<?= e(AdminIcon::ArrowClockwise->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="install.installSteps.refresh_catalog"><?= e($helpers->lang('install.installSteps.refresh_catalog')) ?></span></button>
        </form>
    <?php endif; ?>
</section>
