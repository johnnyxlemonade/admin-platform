<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\DataGrid\DataGridPrimaryAction;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Languages\DataGrid\LanguagesDataGrid;
use Lemonade\Admin\System\Languages\Editor\LanguageEditorDraft;
use Lemonade\Admin\System\Languages\Editor\LanguagesAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use LogicException;

/**
 * Sklada indexovou stranku DataGridu systemovych jazyku
 */
final class LanguagesModulePageProvider implements ModuleIndexPageProviderInterface, ModuleModalEditorPageProviderInterface
{
    public function __construct(
        private readonly LanguagesDataGrid $grid,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly UrlGenerator $urls,
        private readonly LanguagesAdminEditorDefinitionFactory $editorDefinitions,
    ) {}

    public function indexPermission(): string
    {
        return 'system.languages.view';
    }

    /**
     * Pridava create akci jen pro aktera s prislusnym opravnenim
     */
    public function index(string $locale): ModulePage
    {
        $action = $this->authorization->hasPermission('system.languages.create')
            ? new DataGridPrimaryAction(
                translationKey: 'languages.actions.create',
                icon: AdminIcon::PlusLg,
                href: '#',
                modalUrl: $this->urls->route(name: 'admin.api.modal.create', params: ['module' => 'languages']),
                modalSize: 'medium',
            )
            : null;
        $viewModel = new DataGridIndexViewModel(
            moduleCode: 'languages',
            title: $this->translator->get('languages.list.title'),
            description: $this->translator->get('languages.list.description'),
            endpoint: $this->urls->route(name: 'admin.api.datagrid.index', params: ['module' => 'languages']),
            definition: $this->grid->dataGridDefinition(),
            primaryAction: $action,
            loadingText: $this->translator->get('languages.list.loading'),
            emptyText: $this->translator->get('languages.list.empty'),
            errorText: $this->translator->get('admin.datagrid.errorDescription'),
        );

        return new ModulePage(
            view: 'admin::datagrid.index',
            title: $viewModel->title(),
            data: ['dataGrid' => $viewModel, 'locale' => $locale],
        );
    }

    /**
     * Sklada modalni create editor z vychoziho draftu jazyka
     */
    public function modalCreate(EditorLoaded $editor, string $locale): ModulePage
    {
        $language = $editor->data()['language'] ?? null;
        if (!$language instanceof LanguageEditorDraft) {
            throw new LogicException('Languages create editor did not return a language draft.');
        }

        return $this->modalPage(
            titleKey: 'languages.editor.create_title',
            descriptionKey: 'languages.editor.create_description',
            adminEditor: $this->editorDefinitions->modalCreate(),
            values: $language->values(),
            locale: $locale,
            editing: false,
        );
    }

    /**
     * Sklada modalni edit editor z nacteneho zaznamu jazyka
     */
    public function modalEdit(EditorLoaded $editor, string $locale): ModulePage
    {
        $language = $editor->data()['language'] ?? null;
        if (!$language instanceof LanguageRecord) {
            throw new LogicException('Languages edit editor did not return a language record.');
        }

        return $this->modalPage(
            titleKey: 'languages.editor.title',
            descriptionKey: 'languages.editor.description',
            adminEditor: $this->editorDefinitions->modal($language),
            values: $this->values($language),
            locale: $locale,
            editing: true,
        );
    }

    /**
     * Sklada presentation data modalniho editoru jazyku
     *
     * @param array<string, mixed> $values
     */
    private function modalPage(string $titleKey, string $descriptionKey, AdminEditorDefinition $adminEditor, array $values, string $locale, bool $editing): ModulePage
    {
        return new ModulePage(
            view: 'languages::modal-editor',
            title: $this->translator->get($titleKey),
            data: [
                'title' => $this->translator->get($titleKey),
                'titleKey' => $titleKey,
                'description' => $this->translator->get($descriptionKey),
                'descriptionKey' => $descriptionKey,
                'adminEditor' => $adminEditor,
                'adminEditorContext' => new AdminEditorRenderContext(
                    values: $values,
                    oldInput: [],
                    errors: [],
                    mode: $editing ? 'edit' : 'create',
                ),
                'locale' => $locale,
            ],
        );
    }

    /**
     * Prevadi read model na hodnoty ocekavane modalnim editorem
     *
     * @return array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int}
     */
    private function values(LanguageRecord $language): array
    {
        return [
            'id' => $language->id(),
            'code' => $language->code(),
            'name' => $language->name(),
            'flag_code' => $language->flagCode(),
            'enabled' => $language->enabled() ? 1 : 0,
            'is_default' => $language->isDefault() ? 1 : 0,
            'sort_order' => $language->sortOrder(),
        ];
    }
}
