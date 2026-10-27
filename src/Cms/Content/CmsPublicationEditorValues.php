<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Cms\CmsPublicationState;

/**
 * Vraci ulozene publikacni hodnoty ve formatu editorovych poli.
 */
final class CmsPublicationEditorValues
{
    private function __construct() {}

    /**
     * @param list<array<string, mixed>> $translations
     * @return array{publication_state:string,published_at:string}
     */
    public static function forLocale(array $translations, string $locale): array
    {
        foreach ($translations as $translation) {
            if (($translation['locale'] ?? null) !== $locale) {
                continue;
            }

            $date = (string) ($translation['published_at'] ?? '');

            return [
                'publication_state' => CmsPublicationState::from((int) $translation['publication_state'])->editorValue(),
                'published_at' => $date === '' ? '' : str_replace(' ', 'T', substr($date, 0, 16)),
            ];
        }

        return ['publication_state' => CmsPublicationState::New->editorValue(), 'published_at' => ''];
    }
}
