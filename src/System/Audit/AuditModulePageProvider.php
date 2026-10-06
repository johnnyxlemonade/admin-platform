<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Audit\DataGrid\AuditDataGrid;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada indexovou stranku read-only auditniho DataGridu
 */
final class AuditModulePageProvider implements ModuleIndexPageProviderInterface
{
    public function __construct(
        private readonly AuditDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    public function indexPermission(): string
    {
        return 'system.audit.view';
    }

    /**
     * Sestavi stranku s DataGridem a lokalizovanymi stavy jeho nacitani
     */
    public function index(string $locale): ModulePage
    {
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'audit',
            title: $this->translator->get('audit.list.title'),
            description: $this->translator->get('audit.list.description'),
            endpoint: $this->urls->route(
                name: 'admin.api.datagrid.index',
                params: ['module' => 'audit'],
            ),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: null,
            loadingText: $this->translator->get('audit.list.loading'),
            emptyText: $this->translator->get('audit.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }
}
