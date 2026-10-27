<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Layout\AdminEditorTab;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldColumn;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Cms\Content\CmsContentInput;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Validation\ValidationSchema;

/** Shared root display metadata for nonhierarchical CMS editors. */
final class CmsDisplaySettings
{
    private function __construct() {}

    /** @return list<EditorFieldDefinition> */
    public static function fields(): array
    {
        return array_map(
            static fn(string $name): EditorFieldDefinition => new EditorFieldDefinition(name: $name, type: 'text', readOnly: false, permission: null),
            array_keys(CmsContentInput::DISPLAY_DEFAULTS),
        );
    }

    /** @param array<string,mixed> $article
     *  @return array<string,string>
     */
    public static function values(array $article): array
    {
        $values = [];
        foreach (CmsContentInput::DISPLAY_DEFAULTS as $name => $default) {
            $values[$name] = (string) ((int) ($article[$name] ?? $default));
        }

        return $values;
    }

    public static function validation(ValidationSchema $schema, TranslatorInterface $translator): ValidationSchema
    {
        foreach (array_keys(CmsContentInput::DISPLAY_DEFAULTS) as $name) {
            $schema->field($name, $translator->get('admin.cms_display.fields.' . $name));
        }

        return $schema;
    }

    public static function tab(): AdminEditorTab
    {
        $columns = [];
        foreach (array_keys(CmsContentInput::DISPLAY_DEFAULTS) as $name) {
            if ($name === 'recommended') {
                continue;
            }
            $columns[] = new FieldColumn(
                Field::toggle($name, labelKey: 'admin.cms_display.fields.' . $name)
                    ->help('', 'admin.cms_display.fields.' . $name . '_help'),
                md: 6,
            );
        }

        return new AdminEditorTab(
            id: 'settings',
            label: '',
            blocks: [
                new SectionBlock(
                    id: 'recommendation-settings',
                    blocks: [new FieldGroupBlock([new FieldColumn(
                        Field::toggle('recommended', labelKey: 'admin.cms_display.fields.recommended')
                            ->help('', 'admin.cms_display.fields.recommended_help'),
                        md: 12,
                    )])],
                    title: '',
                    titleKey: 'admin.cms_display.sections.recommendation',
                    descriptionKey: 'admin.cms_display.sections.recommendation_help',
                ),
                new SectionBlock(
                    id: 'display-settings',
                    blocks: [new FieldGroupBlock($columns)],
                    title: '',
                    titleKey: 'admin.cms_display.sections.display',
                    descriptionKey: 'admin.cms_display.sections.display_help',
                ),
            ],
            labelKey: 'admin.cms_display.tabs.settings',
        );
    }
}
