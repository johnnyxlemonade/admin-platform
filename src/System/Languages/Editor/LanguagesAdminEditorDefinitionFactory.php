<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Editor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\AdminEditorStatusVariant;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\System\Languages\LanguagePresetCatalog;
use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada modalni AdminEditor pro zalozeni a upravu systemoveho jazyka
 */
final readonly class LanguagesAdminEditorDefinitionFactory
{
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Definuje create formular s editovatelnym business klicem code
     */
    public function modalCreate(): AdminEditorDefinition
    {
        $formAction = $this->urls->route(name: 'admin.system.module.create', params: ['module' => 'languages']);
        $actionUrl = $this->urls->route(name: 'admin.system.module.ajax.create', params: ['module' => 'languages']);

        return AdminEditorBuilder::create('system.languages.modal-create')
            ->form(new AdminActionFormDefinition(
                id: 'languages-modal-create-form',
                action: $formAction,
                actionUrl: $actionUrl,
                actionKey: 'create',
                novalidate: true,
            ))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::select('preset', labelKey: 'languages.fields.preset')
                        ->options(LanguagePresetCatalog::selectOptions())
                        ->help('', 'languages.editor.preset_help')
                        ->attributes([
                            'data-lemonade-searchable' => true,
                            'data-lemonade-language-preset-select' => true,
                            'data-lemonade-language-preset-values' => json_encode(
                                LanguagePresetCatalog::editorValues(),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                            ),
                        ]),
                    md: 12,
                ),
            ]))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::text('name', labelKey: 'languages.fields.name')
                        ->required()
                        ->attributes(['maxlength' => '100']),
                    md: 12,
                ),
            ]))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::text('code', labelKey: 'languages.fields.code')
                        ->required()
                        ->attributes(['maxlength' => '35']),
                    md: 6,
                ),
                new FieldColumn(
                    Field::number('sort_order', labelKey: 'languages.fields.sort_order')
                        ->required()
                        ->attributes(['step' => '1']),
                    md: 6,
                ),
            ]))
            ->build();
    }

    /**
     * Definuje edit formular, kde code a stav zustavaji pouze informativni
     */
    public function modal(LanguageRecord $language): AdminEditorDefinition
    {
        $formAction = $this->urls->route(
            name: 'admin.system.module.edit',
            params: ['module' => 'languages', 'id' => $language->id()],
        );
        $actionUrl = $this->urls->route(
            name: 'admin.system.module.ajax.entity',
            params: ['module' => 'languages', 'id' => $language->id()],
        );
        $isEnabled = $language->enabled();

        return AdminEditorBuilder::create('system.languages.modal')
            ->form(new AdminActionFormDefinition(
                id: 'languages-modal-editor-form',
                action: $formAction,
                actionUrl: $actionUrl,
                actionKey: 'save',
                novalidate: true,
            ))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::text('name', labelKey: 'languages.fields.name')
                        ->required()
                        ->attributes(['maxlength' => '100']),
                    md: 12,
                ),
            ]))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::text('code', labelKey: 'languages.fields.code')
                        ->readonly()
                        ->help('', 'languages.editor.code_read_only'),
                    md: 6,
                ),
                new FieldColumn(
                    Field::statusDisplay(
                        name: 'enabled',
                        labelKey: 'languages.fields.enabled',
                        variant: $isEnabled ? AdminEditorStatusVariant::Success : AdminEditorStatusVariant::Muted,
                    )
                        ->displayValueKey($isEnabled ? 'languages.list.enabled' : 'languages.list.disabled')
                        ->help('', 'languages.editor.enabled_read_only'),
                    md: 6,
                ),
            ]))
            ->block(new FieldGroupBlock([
                new FieldColumn(
                    Field::number('sort_order', labelKey: 'languages.fields.sort_order')
                        ->required()
                        ->attributes(['step' => '1']),
                    md: 4,
                ),
            ]))
            ->build();
    }
}
