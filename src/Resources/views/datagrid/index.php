<?php

declare(strict_types=1);

use Lemonade\Admin\DataGrid\Action\DataGridRowActionRisk;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var ClientTranslationVersion $clientTranslationVersion */
/** @var DataGridIndexViewModel $dataGrid */

$moduleCode = $dataGrid->moduleCode();
$grid = $dataGrid->definition();
$viewFilter = $grid->viewFilter();
$filters = array_values(array_filter($grid->filters(), static fn(DataGridFilterDefinition $filter): bool => $filter !== $viewFilter));
$hasViews = $viewFilter !== null;
$action = $dataGrid->primaryAction();
$bulkActions = $grid->bulkActions();
?>
<div data-lemonade-i18n-namespace="<?= e($moduleCode) ?>" data-lemonade-i18n-namespace-source="<?= e($helpers->url('admin.resources.i18n', ['group' => $moduleCode])) ?>?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="<?= e(json_encode($clientTranslationVersion->versions($moduleCode), JSON_THROW_ON_ERROR)) ?>">
    <header class="page-heading">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><span data-lemonade-i18n="admin.administration.navigation"><?= e($helpers->lang('admin.administration.navigation')) ?></span></li><li class="breadcrumb-item active" aria-current="page" data-lemonade-i18n="<?= e($moduleCode . '.module.name') ?>"><?= e($helpers->lang($moduleCode . '.module.name')) ?></li></ol></nav>
            <h1 data-lemonade-i18n="<?= e($moduleCode . '.list.title') ?>"><?= e($dataGrid->title()) ?></h1>
            <?php if ($dataGrid->description() !== null): ?><p data-lemonade-i18n="<?= e($moduleCode . '.list.description') ?>"><?= e($dataGrid->description()) ?></p><?php endif; ?>
        </div><?php if ($action !== null): ?><a class="btn btn-primary lm-button-primary lm-button-with-icon" href="<?= e($action->href()) ?>"<?php if ($action->modalUrl() !== null): ?> data-lemonade-modal-form-url="<?= e($action->modalUrl()) ?>"<?php if ($action->modalSize() !== null): ?> data-lemonade-modal-size="<?= e($action->modalSize()) ?>"<?php endif; ?><?php endif; ?>><i class="<?= e($action->icon()->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="<?= e($action->translationKey()) ?>"><?= e($helpers->lang($action->translationKey())) ?></span></a><?php endif; ?>
    </header>

    <section aria-label="<?= e($dataGrid->title()) ?>" data-lemonade-grid data-lemonade-grid-key="<?= e($grid->id()) ?>" data-lemonade-source="<?= e($dataGrid->endpoint()) ?>" data-lemonade-default-view="<?= e($grid->defaultView()) ?>" data-lemonade-default-sort="<?= e($grid->defaultSortKey()) ?>" data-lemonade-default-direction="<?= e($grid->defaultSortDirection()) ?>" data-lemonade-default-page-size="<?= e((string) $grid->defaultPageSize()) ?>">
        <div class="toolbar datagrid-toolbar">
            <?php if ($hasViews): ?><nav class="lm-datagrid-views" aria-label="<?= e($dataGrid->title()) ?>"><ul class="lm-datagrid-views-list"><?php if ($grid->showAllView()): ?><li class="lm-datagrid-view-item"><a class="lm-datagrid-view<?= $grid->defaultView() === 'all' ? ' is-active' : '' ?>" href="#" data-lemonade-grid-view="all" data-lemonade-grid-query=""><span data-lemonade-i18n="<?= e($moduleCode . '.list.all') ?>"><?= e($helpers->lang($moduleCode . '.list.all')) ?></span></a></li><?php endif; ?><?php foreach ($viewFilter->optionSource()->options() as $view): ?><li class="lm-datagrid-view-item"><a class="lm-datagrid-view<?= $grid->defaultView() === $view->value() ? ' is-active' : '' ?>" href="#" data-lemonade-grid-view="<?= e($view->value()) ?>" data-lemonade-grid-query="<?= e($viewFilter->key()) ?>=<?= e($view->value()) ?>"><span data-lemonade-i18n="<?= e($moduleCode . '.list.' . $view->value()) ?>"><?= e($view->label()) ?></span></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
            <div class="toolbar-controls datagrid-toolbar-actions<?= $hasViews ? '' : ' ms-auto' ?>">
                <?php if ($grid->searchEnabled()): ?><div class="lm-search search-control datagrid-search"><i class="<?= e(AdminIcon::Search->cssClass()) ?> lm-search-icon" aria-hidden="true"></i><input class="form-control" type="search" placeholder="<?= e($helpers->lang($moduleCode . '.list.search')) ?>" data-lemonade-i18n-placeholder="<?= e($moduleCode . '.list.search') ?>" data-lemonade-grid-search></div><?php endif; ?>
                <?php if ($filters !== []): ?>
                    <div class="lm-filter-dropdown" data-lemonade-dropdown>
                        <button class="btn btn-outline-secondary datagrid-filter" type="button" data-lemonade-dropdown-trigger aria-expanded="false">
                            <i class="<?= e(AdminIcon::Funnel->cssClass()) ?>" aria-hidden="true"></i>
                            <span data-lemonade-i18n="admin.datagrid.filters"><?= e($helpers->lang('admin.datagrid.filters')) ?></span>
                        </button>
                        <div class="lm-dropdown-menu lm-filter-menu" data-lemonade-dropdown-panel hidden data-lemonade-select-floating-root="local">
                            <?php foreach ($filters as $filter): $key = $filter->key(); ?>
                                <div class="lm-filter-group">
                                    <label class="form-label" for="filter-<?= e($grid->id() . '-' . $key) ?>" data-lemonade-i18n="<?= e($moduleCode . '.filters.' . $key) ?>"><?= e($helpers->lang($moduleCode . '.filters.' . $key)) ?></label>
                                    <select class="form-select" id="filter-<?= e($grid->id() . '-' . $key) ?>" data-lemonade-filter="<?= e($key) ?>" data-lemonade-option-source="static">
                                        <option value="" data-lemonade-i18n="<?= e($moduleCode . '.filters.all_' . $key . 's') ?>"><?= e($helpers->lang($moduleCode . '.filters.all_' . $key . 's')) ?></option>
                                        <?php foreach ($filter->optionSource()->options() as $option): ?>
                                            <option value="<?= e($option->value()) ?>"><?= e($option->label()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                            <div class="lm-filter-actions"><button class="btn btn-primary lm-filter-apply" type="button" data-lemonade-grid-filter-apply aria-label="<?= e($helpers->lang('admin.datagrid.applyFilters')) ?>" title="<?= e($helpers->lang('admin.datagrid.applyFilters')) ?>" data-lemonade-i18n-aria-label="admin.datagrid.applyFilters" data-lemonade-i18n-title="admin.datagrid.applyFilters"><i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" aria-hidden="true"></i></button></div>
                        </div>
                    </div>
                <?php endif; ?>
                <button class="btn btn-outline-secondary datagrid-filter lm-datagrid-toolbar-icon" type="button" data-lemonade-grid-reload aria-label="<?= e($helpers->lang('admin.datagrid.refresh')) ?>" title="<?= e($helpers->lang('admin.datagrid.refresh')) ?>" data-lemonade-i18n-aria-label="admin.datagrid.refresh" data-lemonade-i18n-title="admin.datagrid.refresh"><i class="<?= e(AdminIcon::ArrowClockwise->cssClass()) ?>" aria-hidden="true"></i></button>
                <button class="btn btn-outline-secondary datagrid-filter lm-datagrid-toolbar-icon" type="button" data-lemonade-grid-reset hidden aria-label="<?= e($helpers->lang('admin.datagrid.resetView')) ?>" title="<?= e($helpers->lang('admin.datagrid.resetView')) ?>" data-lemonade-i18n-aria-label="admin.datagrid.resetView" data-lemonade-i18n-title="admin.datagrid.resetView"><i class="<?= e(AdminIcon::ArrowCounterclockwise->cssClass()) ?>" aria-hidden="true"></i></button>
            </div>
        </div>
        <?php if ($filters !== []): ?><div class="lm-filter-chips" data-lemonade-grid-filter-chips hidden aria-live="polite"></div><?php endif; ?>
        <?php if ($bulkActions !== []): ?>
            <div class="lm-datagrid-selection-bar" data-lemonade-grid-selection-bar hidden>
                <div class="lm-datagrid-selection-main">
                    <span class="lm-datagrid-selection-count" data-lemonade-grid-selection-count><?= e($helpers->lang('admin.datagrid.selectedCount', ['count' => 0])) ?></span>
                    <span class="lm-datagrid-selection-divider" aria-hidden="true"></span>
                    <div class="lm-datagrid-selection-actions">
                        <?php foreach ($bulkActions as $bulkAction): ?><button class="lm-datagrid-bulk-action<?= $bulkAction->risk() === DataGridRowActionRisk::Destructive ? ' lm-datagrid-bulk-action--danger' : '' ?>" type="button"<?= $bulkAction->download() ? ' data-lemonade-grid-bulk-download' : ' data-lemonade-action' ?> data-lemonade-grid-bulk-action data-lemonade-grid-bulk-views="<?= e(implode(',', $bulkAction->allowedViews())) ?>" data-lemonade-url="<?= e($bulkAction->endpoint()) ?>" data-lemonade-method="POST" data-lemonade-action-key="<?= e($bulkAction->action()) ?>"<?= $bulkAction->confirmation() !== null ? ' data-lemonade-confirm="' . e($bulkAction->confirmation()->messageKey()) . '"' : '' ?><?= $bulkAction->confirmation()?->titleKey() !== null ? ' data-lemonade-confirm-title="' . e($bulkAction->confirmation()->titleKey()) . '"' : '' ?>><i class="<?= e($bulkAction->icon()->cssClass()) ?>" aria-hidden="true"></i> <?= e($bulkAction->label()) ?></button><?php endforeach; ?>
                    </div>
                </div>
                <div class="lm-datagrid-selection-clear-actions"><button class="lm-datagrid-selection-clear" type="button" data-lemonade-grid-clear-selection data-lemonade-i18n="admin.datagrid.clearSelection"><?= e($helpers->lang('admin.datagrid.clearSelection')) ?></button><button class="lm-datagrid-selection-clear" type="button" aria-label="<?= e($helpers->lang('admin.datagrid.clearSelection')) ?>" data-lemonade-grid-clear-selection data-lemonade-i18n-aria-label="admin.datagrid.clearSelection"><i class="<?= e(AdminIcon::XLg->cssClass()) ?>" aria-hidden="true"></i></button></div>
            </div>
        <?php endif; ?>
        <div class="card data-card data-grid<?= $dataGrid->gridClass() !== null ? ' ' . e($dataGrid->gridClass()) : '' ?>">
            <div class="table-responsive"><table class="table lm-datagrid-table align-middle mb-0"><thead><tr><?php if ($grid->selectionEnabled()): ?><th class="check-col"><input class="form-check-input" type="checkbox" aria-label="<?= e($helpers->lang('admin.datagrid.selectAll')) ?>" data-lemonade-i18n-aria-label="admin.datagrid.selectAll" data-lemonade-grid-select-all></th><?php endif; ?><?php foreach ($grid->columns() as $column): $columnClass = trim(($column->class() ?? '') . ($column->key() === 'actions' ? ' lm-datagrid-actions-column' : '')); ?><th<?= $columnClass !== '' ? ' class="' . e($columnClass) . '"' : '' ?><?= $column->sortKey() !== null ? ' aria-sort="none"' : '' ?><?php if ($column->sortKey() === null): ?> data-lemonade-i18n="<?= e($column->translationKey()) ?>"<?php endif; ?>><?php if ($column->sortKey() !== null): ?><button class="lm-datagrid-sort" type="button" data-lemonade-sort="<?= e($column->sortKey()) ?>"><span data-lemonade-i18n="<?= e($column->translationKey()) ?>"><?= e($helpers->lang($column->translationKey())) ?></span><i class="<?= e(AdminIcon::ArrowUp->cssClass()) ?>" aria-hidden="true" data-lemonade-sort-indicator></i></button><?php else: ?><?= e($helpers->lang($column->translationKey())) ?><?php endif; ?></th><?php endforeach; ?></tr></thead><tbody data-lemonade-grid-body></tbody></table></div>
            <div class="lm-grid-state" data-lemonade-grid-loading hidden><span data-lemonade-i18n="<?= e($moduleCode . '.list.loading') ?>"><?= e($dataGrid->loadingText()) ?></span></div>
            <div class="lm-grid-state" data-lemonade-grid-empty hidden><span data-lemonade-i18n="<?= e($moduleCode . '.list.empty') ?>"><?= e($dataGrid->emptyText()) ?></span></div>
            <div class="lm-grid-state lm-grid-state--error" data-lemonade-grid-error hidden><span data-lemonade-i18n="admin.datagrid.errorDescription"><?= e($dataGrid->errorText()) ?></span><button class="btn btn-outline-secondary" type="button" data-lemonade-grid-retry data-lemonade-i18n="admin.datagrid.retry"><?= e($helpers->lang('admin.datagrid.retry')) ?></button></div>
            <div class="card-footer table-footer data-grid-footer"><div class="pagination-wrap"><span data-lemonade-grid-summary></span><nav aria-label="Pagination"><ul class="pagination lm-pagination mb-0" data-lemonade-grid-pagination></ul></nav></div></div>
        </div>
    </section>
</div>
