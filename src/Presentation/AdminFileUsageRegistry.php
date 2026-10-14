<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Eviduje modulove deklarace file usage pred finalizaci Admin bootstrapu
 */
final class AdminFileUsageRegistry
{
    private const string ThumbnailUsage = 'thumbnail';
    private const string GalleryUsage = 'gallery';
    private const string AttachmentUsage = 'attachment';
    private const string RichTextImagesUsage = 'content_image';
    private const string RichTextFilesUsage = 'content_file';

    /** @var array<string,AdminFileUsageDefinition> */
    private array $definitions = [];

    /**
     * Prida jedinecnou deklaraci usage
     */
    public function register(AdminFileUsageDefinition $definition): void
    {
        $key = $definition->moduleCode() . ':' . $definition->usage();
        if (isset($this->definitions[$key])) {
            throw new \LogicException(sprintf('File usage "%s" is already registered.', $key));
        }

        $this->definitions[$key] = $definition;
    }

    /**
     * Rozvine modulovou capability na interni usage kontrakty
     */
    public function registerCapabilities(string $moduleCode, AdminFileCapabilities $capabilities): void
    {
        if ($capabilities->thumbnail !== null) {
            $this->register(new AdminFileUsageDefinition(
                moduleCode: $moduleCode,
                usage: self::ThumbnailUsage,
                kind: 'image',
                profile: 'admin-image',
                multiple: false,
                sortable: false,
                presentation: $capabilities->thumbnail,
            ));
        }
        if ($capabilities->gallery) {
            $this->register(new AdminFileUsageDefinition(
                moduleCode: $moduleCode,
                usage: self::GalleryUsage,
                kind: 'image',
                profile: 'admin-image',
                multiple: true,
                sortable: true,
            ));
        }
        if ($capabilities->attachments) {
            $this->register(new AdminFileUsageDefinition(
                moduleCode: $moduleCode,
                usage: self::AttachmentUsage,
                kind: 'file',
                profile: 'admin-file',
                multiple: true,
                sortable: true,
                imageProfile: 'admin-image',
            ));
        }
        if ($capabilities->richTextImages) {
            $this->register(new AdminFileUsageDefinition(
                moduleCode: $moduleCode,
                usage: self::RichTextImagesUsage,
                kind: 'image',
                profile: 'admin-image',
                multiple: true,
                sortable: false,
            ));
        }
        if ($capabilities->richTextFiles) {
            $this->register(new AdminFileUsageDefinition(
                moduleCode: $moduleCode,
                usage: self::RichTextFilesUsage,
                kind: 'file',
                profile: 'admin-file',
                multiple: true,
                sortable: false,
                imageProfile: 'admin-image',
            ));
        }
    }

    /**
     * Vraci interni usage rich-text obrazku deklarovanou modulem
     */
    public function richTextImage(string $moduleCode): AdminFileUsageDefinition
    {
        return $this->require($moduleCode, self::RichTextImagesUsage);
    }

    /**
     * Vraci interni usage rich-text souboru deklarovanou modulem
     */
    public function richTextFile(string $moduleCode): AdminFileUsageDefinition
    {
        return $this->require($moduleCode, self::RichTextFilesUsage);
    }

    /**
     * Vrati deklaraci usage nebo odmitne neznamy transportni target
     */
    public function require(string $moduleCode, string $usage): AdminFileUsageDefinition
    {
        $definition = $this->definitions[$moduleCode . ':' . $usage] ?? null;
        if ($definition === null) {
            throw new \OutOfBoundsException('File usage is not registered.');
        }

        return $definition;
    }
}
