<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada indexovou stranku management DataGridu nad runtime stavy modulu
 */
final class ModulesModulePageProvider implements ModuleIndexPageProviderInterface
{
    /**
     * Nastavuje DataGrid a lokalizovane texty indexove prezentace
     */
    public function __construct(
        private readonly ModulesDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    public function indexPermission(): string
    {
        return 'system.modules.view';
    }

    /**
     * Sestavuje DataGrid stranku pro aktivni jazyk administrace
     */
    public function index(string $locale): ModulePage
    {
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'modules',
            title: $this->translator->get('modules.list.title'),
            description: $this->translator->get('modules.list.description'),
            endpoint: $this->urls->route(
                name: 'admin.api.datagrid.index',
                params: ['module' => 'modules'],
            ),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: null,
            loadingText: $this->translator->get('modules.list.loading'),
            emptyText: $this->translator->get('modules.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }
}
