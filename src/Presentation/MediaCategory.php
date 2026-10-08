<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Klasifikuje serverem overeny MIME typ pro katalog souboru
 */
enum MediaCategory: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Other = 'other';

    /**
     * Urci stabilni media kategorii z ulozeneho MIME typu
     */
    public static function classify(string $mimeType): self
    {
        $normalizedMimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));

        if (str_starts_with($normalizedMimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($normalizedMimeType, 'video/')) {
            return self::Video;
        }

        if (str_starts_with($normalizedMimeType, 'text/') || in_array($normalizedMimeType, self::documentMimeTypes(), true)) {
            return self::Document;
        }

        return self::Other;
    }

    /**
     * Vrati standardni MIME typy kancelarskych a OpenDocument souboru
     *
     * @return list<string>
     */
    private static function documentMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/rtf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
        ];
    }
}
