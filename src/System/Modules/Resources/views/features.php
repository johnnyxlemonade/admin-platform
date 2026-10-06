<?php

declare(strict_types=1);

use Lemonade\Admin\System\Modules\ViewModels\ModuleFeaturesPageViewModel;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var ModuleFeaturesPageViewModel $page
 */
?>
<div class="module-features-page">
    <header class="page-heading">
        <div>
            <nav aria-label="<?= e($helpers->lang('modules.features.breadcrumb')) ?>">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= e($helpers->url('admin.system.module.index', ['module' => 'modules'])) ?>"><?= e($helpers->lang('modules.module.name')) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($helpers->lang('modules.features.title')) ?></li>
                </ol>
            </nav>
            <h1><?= e($helpers->lang('modules.features.title')) ?></h1>
            <p><?= e($helpers->lang('modules.features.description')) ?></p>
        </div>
    </header>

    <section class="card lm-card mb-4" aria-labelledby="module-features-module">
        <div class="card-body">
            <h2 id="module-features-module" class="h5 mb-3"><?= e($page->moduleName()) ?></h2>
            <dl class="row mb-0">
                <dt class="col-sm-3"><?= e($helpers->lang('modules.fields.code')) ?></dt>
                <dd class="col-sm-9"><code><?= e($page->moduleCode()) ?></code></dd>
                <dt class="col-sm-3"><?= e($helpers->lang('modules.fields.kind')) ?></dt>
                <dd class="col-sm-9"><?= e($page->moduleKindLabel()) ?></dd>
                <dt class="col-sm-3"><?= e($helpers->lang('modules.fields.state')) ?></dt>
                <dd class="col-sm-9"><?= e($page->lifecycleStateLabel()) ?></dd>
            </dl>
        </div>
    </section>

    <?php if ($page->notice() !== null): ?>
        <div class="alert alert-info" role="status"><?= e($page->notice()) ?></div>
    <?php endif; ?>

    <section class="card lm-card" aria-labelledby="module-features-list">
        <div class="card-body">
            <h2 id="module-features-list" class="h5 mb-4"><?= e($helpers->lang('modules.features.listTitle')) ?></h2>
            <?php if ($page->features() === []): ?>
                <p class="mb-0"><?= e($helpers->lang('modules.features.empty')) ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= e($helpers->lang('modules.features.fields.feature')) ?></th>
                                <th><?= e($helpers->lang('modules.features.fields.category')) ?></th>
                                <th><?= e($helpers->lang('modules.features.fields.configured')) ?></th>
                                <th><?= e($helpers->lang('modules.features.fields.effective')) ?></th>
                                <th><?= e($helpers->lang('admin.common.actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page->features() as $feature): ?>
                                <?php $action = $feature->action(); ?>
                                <tr>
                                    <th scope="row">
                                        <?= e($feature->label()) ?>
                                        <?php if ($feature->required()): ?><span class="badge text-bg-secondary ms-2"><?= e($helpers->lang('modules.features.required')) ?></span><?php endif; ?>
                                    </th>
                                    <td><?= e($feature->categoryLabel()) ?></td>
                                    <td><?= e($feature->configuredStateLabel()) ?></td>
                                    <td><?= e($feature->effectiveStateLabel()) ?></td>
                                    <td>
                                        <?php if ($action !== null): ?>
                                            <button
                                                class="btn btn-sm <?= $action->enabled() ? 'btn-primary' : 'btn-outline-secondary' ?>"
                                                type="button"
                                                data-lemonade-action
                                                data-lemonade-url="<?= e($action->url()) ?>"
                                                data-lemonade-method="POST"
                                                data-lemonade-payload="<?= e(json_encode(['action' => 'feature-toggle', 'payload' => ['module_code' => $page->moduleCode(), 'feature_code' => $feature->code(), 'enabled' => $action->enabled()]], JSON_THROW_ON_ERROR)) ?>"
                                            ><?= e($action->label()) ?></button>
                                        <?php else: ?>
                                            <span class="text-muted"><?= e($feature->readOnlyLabel() ?? $helpers->lang('modules.features.readOnly.capability')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
