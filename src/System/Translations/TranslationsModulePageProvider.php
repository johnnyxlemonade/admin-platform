<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Translations\DataGrid\TranslationsDataGrid;
use Lemonade\Admin\System\Translations\Editor\TranslationsAdminEditorDefinitionFactory;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use LogicException;

/**
 * Sklada indexovou a editorovou stranku managementu runtime prekladu
 */
final class TranslationsModulePageProvider implements ModuleIndexPageProviderInterface, ModuleModalEditorPageProviderInterface
{
    /**
     * Nastavuje grid a lokalizovanou presentation indexu
     */
    public function __construct(
        private readonly TranslationsDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
        private readonly TranslationsAdminEditorDefinitionFactory $editorDefinitions,
    ) {}

    /**
     * Vraci pravo potrebne pro indexovou projekci
     */
    public function indexPermission(): string
    {
        return 'system.translations.view';
    }

    /**
     * Sklada DataGrid bez create akce, protoze source katalog je read-only
     */
    public function index(string $locale): ModulePage
    {
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'translations',
            title: $this->translator->get('translations.list.title'),
            description: $this->translator->get('translations.list.description'),
            endpoint: $this->urls->route('admin.api.datagrid.index', ['module' => 'translations']),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: null,
            loadingText: $this->translator->get('translations.list.loading'),
            emptyText: $this->translator->get('translations.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage('admin::datagrid.index', $viewModel->title(), ['dataGrid' => $viewModel, 'locale' => $locale]);
    }

    /**
     * Odmita modalni create, protoze source katalog prekladu je read-only
     */
    public function modalCreate(EditorLoaded $editor, string $locale): ModulePage
    {
        throw new LogicException('Translations does not support modal creation.');
    }

    /**
     * Sklada modalni edit presentation identity prekladu
     */
    public function modalEdit(EditorLoaded $editor, string $locale): ModulePage
    {
        $translation = $editor->data()['translation'] ?? null;
        if (!is_array($translation)) {
            throw new LogicException('Translations editor did not return a translation projection.');
        }

        /** @var array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool} $translation */
        return new ModulePage(
            view: 'translations::modal-editor',
            title: $this->translator->get('translations.editor.title'),
            data: [
                'title' => $this->translator->get('translations.editor.title'),
                'titleKey' => 'translations.editor.title',
                'description' => $this->translator->get('translations.editor.description'),
                'descriptionKey' => 'translations.editor.description',
                'adminEditor' => $this->editorDefinitions->modal($translation),
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $translation + ['value' => $translation['overrideValue'] ?? ''],
                    oldInput: [],
                    errors: [],
                    mode: 'edit',
                ),
                'locale' => $locale,
            ],
        );
    }
}
