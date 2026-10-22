<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Editor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada modalni AdminEditor pro zmenu jedine vlastni hodnoty prekladu
 */
final readonly class TranslationsAdminEditorDefinitionFactory
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
            ->form(new AdminActionFormDefinition(
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
                        new FieldColumn(Field::readonlyDisplay('locale', labelKey: 'translations.fields.locale')->asCode(), md: 3),
                        new FieldColumn(Field::readonlyDisplay('owner', labelKey: 'translations.fields.owner')->asCode(), md: 3),
                        new FieldColumn(Field::readonlyDisplay('group', labelKey: 'translations.fields.group')->asCode(), md: 3),
                        new FieldColumn(Field::readonlyDisplay('key', labelKey: 'translations.fields.key')->asCode(), md: 3),
                    ]),
                ],
                title: '',
                titleKey: 'translations.editor.identity',
                description: '',
                descriptionKey: 'translations.editor.identity_help',
            ))
            ->block(new SectionBlock(
                id: 'values',
                blocks: [
                    Field::textarea('source', labelKey: 'translations.fields.source')->readonly()->attributes(['rows' => '4']),
                    Field::textarea('effective', labelKey: 'translations.fields.effective')->readonly()->attributes(['rows' => '4']),
                    Field::textarea('value', labelKey: 'translations.fields.override')->attributes(['rows' => '5']),
                ],
                title: '',
                titleKey: 'translations.editor.values',
                description: '',
                descriptionKey: 'translations.editor.values_help',
            ))
            ->build();
    }
}
