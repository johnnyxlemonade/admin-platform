<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Field\Field;

/**
 * Sklada spolecnou SEO zalozku obsahovych editoru
 */
final class SeoSection
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori SEO pole se samostatnou akci zmeny canonical URL
     */
    public static function tab(string $pathPrefix, bool $autoGenerateUrl): AdminEditorTab
    {
        return new AdminEditorTab(
            id: 'seo',
            label: '',
            blocks: [new SectionBlock(
                id: 'seo',
                blocks: [new FieldGroupBlock([
                    new FieldColumn(
                        Field::text('page_title', labelKey: 'admin.seo.page_title')
                            ->attributes(['maxlength' => '180'])
                            ->help('', 'admin.seo.page_title_help'),
                        md: 12,
                    ),
                    new FieldColumn(
                        Field::textarea('meta_description', labelKey: 'admin.seo.meta_description')
                            ->attributes(['maxlength' => '255'])
                            ->help('', 'admin.seo.meta_description_help'),
                        md: 12,
                    ),
                    new FieldColumn(
                        Field::urlSegment('url_segment')
                            ->attributes(['maxlength' => '180'])
                            ->prefix($pathPrefix)
                            ->source(field: 'title', autoGenerate: $autoGenerateUrl)
                            ->action('change-url'),
                        md: 12,
                    ),
                ])],
                title: '',
                titleKey: 'admin.seo.section',
                description: '',
                descriptionKey: 'admin.seo.section_help',
            )],
            labelKey: 'admin.seo.tab',
        );
    }
}
