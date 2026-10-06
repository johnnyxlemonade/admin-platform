<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Editor;

use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\SectionBlock;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada modalni AdminEditor pro zmenu jedine vlastni hodnoty prekladu
 */
final class TranslationsAdminEditorDefinitionFactory
{
    /**
     * Nastavuje generator canonical Admin routes
     */
    public function __construct(private readonly UrlGenerator $urls) {}

    /**
     * Vytvari modal s read-only identitou a editovatelnou vlastni hodnotou
     *
     * @param array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool} $translation
     */
    public function modal(array $translation): AdminEditorDefinition
    {
        $formAction = $this->urls->route('admin.system.module.edit', ['module' => 'translations', 'id' => $translation['id']]);
        $actionUrl = $this->urls->route('admin.system.module.ajax.entity', ['module' => 'translations', 'id' => $translation['id']]);

        return AdminEditorBuilder::create('system.translations.modal')
            ->form(new AdminEditorFormDefinition(
                id: 'translations-modal-editor-form',
                action: $formAction,
                actionUrl: $actionUrl,
                actionKey: 'save',
                novalidate: true,
            ))
            ->block(new SectionBlock(
                id: 'identity',
                blocks: [
                    new FieldGroupBlock([
                        new FieldColumn(AdminEditorFieldDefinition::readonlyDisplay('locale', labelKey: 'translations.fields.locale')->asCode(), md: 3),
                        new FieldColumn(AdminEditorFieldDefinition::readonlyDisplay('owner', labelKey: 'translations.fields.owner')->asCode(), md: 3),
                        new FieldColumn(AdminEditorFieldDefinition::readonlyDisplay('group', labelKey: 'translations.fields.group')->asCode(), md: 3),
                        new FieldColumn(AdminEditorFieldDefinition::readonlyDisplay('key', labelKey: 'translations.fields.key')->asCode(), md: 3),
                    ]),
                ],
                title: '',
                titleKey: 'translations.editor.identity',
            ))
            ->block(new SectionBlock(
                id: 'values',
                blocks: [
                    AdminEditorFieldDefinition::textarea('source', labelKey: 'translations.fields.source')->readonly()->attributes(['rows' => '4']),
                    AdminEditorFieldDefinition::textarea('effective', labelKey: 'translations.fields.effective')->readonly()->attributes(['rows' => '4']),
                    AdminEditorFieldDefinition::textarea('value', labelKey: 'translations.fields.override')->attributes(['rows' => '5']),
                ],
                title: '',
                titleKey: 'translations.editor.values',
            ))
            ->build();
    }
}
