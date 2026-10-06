<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media;

use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Media\DataGrid\MediaDataGrid;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada indexovou stranku centralniho Media DataGridu
 */
final class MediaModulePageProvider implements ModuleIndexPageProviderInterface
{
    public function __construct(
        private readonly MediaDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    public function indexPermission(): string
    {
        return 'system.media.view';
    }

    /**
     * Vytvari prazdny i naplneny katalog pres sdileny DataGrid transport
     */
    public function index(string $locale): ModulePage
    {
        $viewModel = new DataGridIndexViewModel(moduleCode: 'media', title: $this->translator->get('media.list.title'), description: $this->translator->get('media.list.description'), endpoint: $this->urls->route(name: 'admin.api.datagrid.index', params: ['module' => 'media']), definition: $this->grid->dataGridDefinition(), primaryAction: null, loadingText: $this->translator->get('media.list.loading'), emptyText: $this->translator->get('media.list.empty'), errorText: $this->translator->get('admin.datagrid.errorDescription'));

        return new ModulePage(view: 'admin::datagrid.index', title: $viewModel->title(), data: ['dataGrid' => $viewModel, 'locale' => $locale]);
    }
}
