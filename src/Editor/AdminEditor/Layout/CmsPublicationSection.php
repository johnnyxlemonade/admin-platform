<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Cms\CmsPublicationState;

/**
 * Sklada publikacni panel podle opravneni editora
 */
final class CmsPublicationSection
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori publikacni pole pouze pri opravneni k publikovani
     */
    public static function create(string $module, bool $canPublish): ?SectionBlock
    {
        if (!$canPublish) {
            return null;
        }

        return new SectionBlock(
            id: 'publication',
            blocks: [new FieldGroupBlock([
                new FieldColumn(
                    Field::select('publication_state', labelKey: $module . '.fields.publication_state')
                        ->options(array_fill_keys(array_keys(CmsPublicationState::editorOptionLabelKeys()), ''))
                        ->optionLabelKeys(CmsPublicationState::editorOptionLabelKeys()),
                    md: 12,
                ),
                new FieldColumn(Field::datetimeLocal('published_at', labelKey: $module . '.fields.published_at'), md: 12),
            ])],
            title: '',
            titleKey: $module . '.editor.publication',
            descriptionKey: $module . '.editor.sections.publication_help',
        );
    }
}
