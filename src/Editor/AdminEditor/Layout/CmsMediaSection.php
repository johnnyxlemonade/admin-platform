<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Presentation\AdminFileUploadCollection;

/**
 * Sklada spolecne zalozky medii a panel nahledoveho obrazku CMS editoru
 */
final class CmsMediaSection
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori zalozku galerie vcetne prazdneho stavu
     */
    public static function gallery(string $module, ?AdminFileUploadCollection $upload): AdminEditorTab
    {
        return self::tab('gallery', 'admin.file_upload.gallery', $module . '.editor.sections.gallery_help', $upload);
    }

    /**
     * Vytvori zalozku priloh vcetne prazdneho stavu
     */
    public static function attachments(string $module, ?AdminFileUploadCollection $upload): AdminEditorTab
    {
        return self::tab('attachments', 'admin.file_upload.attachments', $module . '.editor.sections.attachments_help', $upload);
    }

    /**
     * Vytvori panel nahledoveho obrazku, pokud je dostupna upload kolekce
     */
    public static function thumbnail(string $module, ?AdminFileUploadCollection $upload): ?SectionBlock
    {
        if ($upload === null) {
            return null;
        }

        return new SectionBlock(
            id: 'thumbnail',
            blocks: [new CustomViewBlock('admin::components.file-upload-collection', ['uploadCollection' => $upload])],
            title: '',
            titleKey: $module . '.editor.thumbnail.title',
            descriptionKey: $module . '.editor.sections.thumbnail_help',
        );
    }

    /**
     * Sklada zalozku pro jednu media kolekci
     */
    private static function tab(string $id, string $labelKey, string $descriptionKey, ?AdminFileUploadCollection $upload): AdminEditorTab
    {
        return new AdminEditorTab(
            id: $id,
            label: '',
            blocks: [new SectionBlock(
                id: $id,
                blocks: $upload === null ? [] : [new CustomViewBlock('admin::components.file-upload-collection', ['uploadCollection' => $upload])],
                title: '',
                titleKey: $labelKey,
                descriptionKey: $descriptionKey,
            )],
            labelKey: $labelKey,
        );
    }
}
