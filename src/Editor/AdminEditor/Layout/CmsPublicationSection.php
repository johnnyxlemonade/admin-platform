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
     * Popise podminenou povinnost vcetne ochrany sdilene hodnoty
     *
     * @return array<string,string>
     */
    public static function requiredAttributes(
        int $state,
        bool $canPublish,
        bool $globallyRequired = false,
        string $messageKey = 'admin.cms_validation.required',
    ): array {
        $attributes = [
            'data-lemonade-required-message' => $messageKey,
            'data-lemonade-required-active' => $globallyRequired || $state === CmsPublicationState::Published->value ? '1' : '0',
        ];
        if ($canPublish && !$globallyRequired) {
            $attributes['data-lemonade-required-when'] = 'publication_state';
            $attributes['data-lemonade-required-value'] = (string) CmsPublicationState::Published->value;
        }

        return $attributes;
    }

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
                        ->optionLabelKeys(CmsPublicationState::editorOptionLabelKeys())
                        ->help('', 'admin.editor.publication_state_help'),
                    md: 12,
                ),
                new FieldColumn(
                    Field::datetimeLocal('published_at', labelKey: $module . '.fields.published_at')
                        ->help('', 'admin.editor.published_at_help'),
                    md: 12,
                ),
            ])],
            title: '',
            titleKey: $module . '.editor.publication',
            descriptionKey: $module . '.editor.sections.publication_help',
        );
    }
}
