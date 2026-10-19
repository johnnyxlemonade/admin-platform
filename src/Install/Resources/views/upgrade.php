<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Install\InstallStepStatus;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var View $this */
/** @var ClientTranslationVersion $clientTranslationVersion */
/** @var array{ready:bool,checks:list<array{key:string,ok:bool,status:string,detail:string}>} $preflight */
/** @var list<array{step:string,status:InstallStepStatus,messageKey:string}> $stepStates */
/** @var array{success:bool,steps:list<array{key:string,ok:bool,error:string}>,rootCreated:bool}|null $result */
/** @var string|null $generalError */
/** @var string $workflowEndpoint */
/** @var string $workflowCompletedUrl */

$preflightReady = $preflight['ready'];
?>
<?php $this->start('title'); ?><?= e($helpers->lang('admin.account.system_upgrade')) ?><?php $this->end(); ?>
<div data-lemonade-i18n-namespace="install" data-lemonade-i18n-namespace-source="<?= e($helpers->url('admin.resources.i18n', ['group' => 'install'])) ?>?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="<?= e(json_encode($clientTranslationVersion->versions('install'), JSON_THROW_ON_ERROR)) ?>">
    <section data-lemonade-install data-install-initial="0" data-install-preflight-ready="<?= $preflightReady ? '1' : '0' ?>" data-install-endpoint="<?= e($workflowEndpoint) ?>" data-install-login-url="<?= e($workflowCompletedUrl) ?>" data-install-generic-error="<?= e($helpers->lang('install.install.result.failure')) ?>">
        <header class="page-heading">
            <div>
                <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e($helpers->url('admin.dashboard')) ?>" data-lemonade-i18n="admin.navigation.dashboard"><?= e($helpers->lang('admin.navigation.dashboard')) ?></a></li><li class="breadcrumb-item active" aria-current="page" data-lemonade-i18n="admin.account.system_upgrade"><?= e($helpers->lang('admin.account.system_upgrade')) ?></li></ol></nav>
                <h1 data-lemonade-i18n="admin.account.system_upgrade"><?= e($helpers->lang('admin.account.system_upgrade')) ?></h1>
                <p data-lemonade-i18n="admin.account.system_upgrade_description"><?= e($helpers->lang('admin.account.system_upgrade_description')) ?></p>
            </div>
        </header>

        <div class="lemonade-auth lemonade-auth--embedded">
            <div class="lemonade-install">
                <?= $this->partial('admin-install::partials.workflow', ['showEnvironment' => false, 'headingPrefix' => 'upgrade', 'preflight' => $preflight, 'stepStates' => $stepStates, 'generalError' => $generalError, 'result' => $result]) ?>

                <?php if (!$preflightReady): ?><div class="lemonade-install-preflight-blocked alert alert-warning" role="alert"><i class="<?= e(AdminIcon::ExclamationTriangle->cssClass()) ?>" aria-hidden="true"></i><span><?= e($helpers->lang('install.installErrors.preflight')) ?></span></div><?php endif; ?>
                <?php if ($preflightReady && ($result === null || !$result['success'])): ?>
                    <form class="lemonade-install-form lm-auth-form" method="post" action="<?= e($workflowEndpoint) ?>" data-install-form data-install-progressive><?= $helpers->csrfField() ?><button class="btn btn-primary lm-auth-submit lemonade-install-submit" type="submit" data-install-submit><span data-install-submit-label data-lemonade-i18n="install.install.actions.update"><?= e($helpers->lang('install.install.actions.update')) ?></span><span class="spinner-border spinner-border-sm" data-install-submit-spinner role="status" aria-hidden="true" hidden></span></button></form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
