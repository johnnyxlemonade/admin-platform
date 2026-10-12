<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Install\InstallStepStatus;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var View $this */
/** @var bool $showEnvironment */
/** @var string $headingPrefix */
/** @var array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>} $preflight */
/** @var list<array{step:string,status:InstallStepStatus,messageKey:string}> $stepStates */
/** @var string|null $generalError */
/** @var array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}|null $result */

$iconForStatus = static function (string $status): string {
    return match ($status) {
        'success' => AdminIcon::CheckCircle->cssClass(),
        'error' => AdminIcon::XCircle->cssClass(),
        default => AdminIcon::Circle->cssClass(),
    };
};
$passedChecks = count(array_filter($preflight['checks'], static fn(array $check): bool => $check['ok']));
$totalChecks = count($preflight['checks']);
$environmentStatus = $preflight['ready'] ? 'success' : 'error';
?>
<?php if ($generalError !== null): ?><div class="lemonade-install-feedback alert alert-danger" role="alert"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($generalError) ?></span></div><?php endif; ?>
<?php if ($result !== null): ?><div class="lemonade-install-feedback alert <?= $result['success'] ? 'alert-success' : 'alert-danger' ?>" role="status"><i class="<?= e($result['success'] ? AdminIcon::CheckCircle->cssClass() : AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($helpers->lang($result['success'] ? 'install.install.result.success' : 'install.install.result.failure')) ?></span></div><?php endif; ?>

<section class="lemonade-install-section" aria-labelledby="<?= e($headingPrefix) ?>-preflight-title">
    <div class="lemonade-install-section-heading">
        <div>
            <span class="lemonade-install-section-kicker" data-lemonade-i18n="install.install.preflight.title"><?= e($helpers->lang('install.install.preflight.title')) ?></span>
            <h2 id="<?= e($headingPrefix) ?>-preflight-title" data-lemonade-i18n="install.install.preflight.title"><?= e($helpers->lang('install.install.preflight.title')) ?></h2>
        </div>
        <span class="lemonade-install-summary" role="status" data-lemonade-i18n="install.installPresentation.preflight_summary" data-lemonade-i18n-param-passed="<?= e((string) $passedChecks) ?>" data-lemonade-i18n-param-total="<?= e((string) $totalChecks) ?>"><?= e($helpers->lang('install.installPresentation.preflight_summary', ['passed' => $passedChecks, 'total' => $totalChecks])) ?></span>
    </div>
    <div class="row g-2 lemonade-install-checks">
        <?php foreach ($preflight['checks'] as $check): ?>
            <?php $checkStatus = $check['status']; ?>
            <?php $checkIcon = match ($checkStatus) {
                'success' => AdminIcon::CheckCircle->cssClass(), 'error' => AdminIcon::XCircle->cssClass(), default => AdminIcon::Circle->cssClass()
            }; ?>
            <div class="col-12 col-md-6"><div class="lemonade-install-check lemonade-install-check--<?= e($checkStatus) ?>"><i class="lemonade-install-check-icon <?= e($checkIcon) ?>" aria-hidden="true"></i><div class="lemonade-install-check-copy"><span class="lemonade-install-check-label" data-lemonade-i18n="install.install.checks.<?= e($check['key']) ?>"><?= e($helpers->lang('install.install.checks.' . $check['key'])) ?></span><span class="lemonade-install-check-status" data-lemonade-i18n="install.install.status.<?= e($checkStatus) ?>"><?= e($helpers->lang('install.install.status.' . $checkStatus)) ?></span><?php if ($checkStatus !== 'success' && $check['detail'] !== ''): ?><span class="lemonade-install-check-detail" role="note"><?= e($check['detail']) ?></span><?php endif; ?></div></div></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="lemonade-install-section lemonade-install-stepper" aria-labelledby="<?= e($headingPrefix) ?>-steps-title">
    <div class="lemonade-install-section-heading lemonade-install-section-heading--steps"><div><span class="lemonade-install-section-kicker" data-lemonade-i18n="install.installPresentation.progress"><?= e($helpers->lang('install.installPresentation.progress')) ?></span><h2 id="<?= e($headingPrefix) ?>-steps-title" data-lemonade-i18n="install.install.steps.title"><?= e($helpers->lang('install.install.steps.title')) ?></h2></div><span class="lemonade-install-summary" data-install-progress-label data-install-progress-template="<?= e($helpers->lang('install.installPresentation.progress_completed')) ?>"><?= e($helpers->lang('install.installSteps.pending')) ?></span></div>
    <div class="progress lemonade-install-progress" role="progressbar" aria-label="<?= e($helpers->lang('install.installPresentation.progress')) ?>" data-lemonade-i18n-aria-label="install.installPresentation.progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-install-progress><div class="progress-bar" data-install-progress-bar></div></div>
    <ol class="lemonade-install-steps" data-install-steps aria-live="polite">
        <?php if ($showEnvironment): ?><li class="lemonade-install-step" data-install-step="environment" data-install-status="<?= e($environmentStatus) ?>" data-install-status-pending="<?= e($helpers->lang('install.installSteps.pending')) ?>" data-install-status-running="<?= e($helpers->lang('install.installSteps.running')) ?>" data-install-status-success="<?= e($helpers->lang('install.installSteps.success')) ?>" data-install-status-error="<?= e($helpers->lang('install.installSteps.error')) ?>" data-install-status-skipped="<?= e($helpers->lang('install.installSteps.skipped')) ?>"><span class="lemonade-install-step-rail"><span data-install-step-icon class="lemonade-install-step-icon <?= e($iconForStatus($environmentStatus)) ?>" aria-hidden="true"></span><span class="lemonade-install-step-line" aria-hidden="true"></span></span><span class="lemonade-install-step-copy"><span data-install-step-label class="lemonade-install-step-label" data-lemonade-i18n="install.installSteps.environment"><?= e($helpers->lang('install.installSteps.environment')) ?></span><span data-install-step-message class="lemonade-install-step-message" role="status"></span></span><strong data-install-step-status class="badge lemonade-install-badge lemonade-install-badge--<?= e($environmentStatus) ?>"><?= e($helpers->lang('install.installSteps.' . $environmentStatus)) ?></strong></li><?php endif; ?>
        <?php foreach ($stepStates as $step): ?><?php if ($step['step'] !== 'root' || $showEnvironment): ?><?php $status = $step['status']->value; ?><li class="lemonade-install-step" data-install-step="<?= e($step['step']) ?>" data-install-status="<?= e($status) ?>" data-install-status-pending="<?= e($helpers->lang('install.installSteps.pending')) ?>" data-install-status-running="<?= e($helpers->lang('install.installSteps.running')) ?>" data-install-status-success="<?= e($helpers->lang('install.installSteps.success')) ?>" data-install-status-error="<?= e($helpers->lang('install.installSteps.error')) ?>" data-install-status-skipped="<?= e($helpers->lang('install.installSteps.skipped')) ?>"><span class="lemonade-install-step-rail"><span data-install-step-icon class="lemonade-install-step-icon <?= e($iconForStatus($status)) ?>" aria-hidden="true"></span><span class="lemonade-install-step-line" aria-hidden="true"></span></span><span class="lemonade-install-step-copy"><span data-install-step-label class="lemonade-install-step-label" data-lemonade-i18n="install.install.steps.<?= e($step['step']) ?>"><?= e($helpers->lang('install.install.steps.' . $step['step'])) ?></span><span data-install-step-message class="lemonade-install-step-message" role="status"></span></span><strong data-install-step-status class="badge lemonade-install-badge lemonade-install-badge--<?= e($status) ?>"><?= e($helpers->lang('install.installSteps.' . $status)) ?></strong></li><?php endif; ?><?php endforeach; ?>
    </ol>
    <?php if ($result !== null): ?><div class="lemonade-install-results" data-install-results aria-live="polite"><?php foreach ($result['steps'] as $resultStep): ?><?php if (!$resultStep['ok'] && $resultStep['error'] !== ''): ?><p data-install-result-step="<?= e($resultStep['key']) ?>"><i class="<?= e(AdminIcon::XCircle->cssClass()) ?>" aria-hidden="true"></i><?= e($resultStep['error']) ?></p><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
</section>
