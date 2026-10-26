<?php

declare(strict_types=1);

namespace Lemonade\Admin\File;

/**
 * Poskytuje typovana metadata jednoho souboru ze sdileneho katalogu
 */
final readonly class FileMetadata
{
    /**
     * Nastavuje identitu, uloziste a presentation metadata souboru
     */
    public function __construct(
        private int $id,
        private string $moduleCode,
        private int $entityId,
        private string $usage,
        private string $kind,
        private string $mediaCategory,
        private ?string $assetId,
        private ?string $sourceVersion,
        private ?string $storagePath,
        private string $originalFilename,
        private ?string $displayName,
        private ?string $caption,
        private string $extension,
        private string $mimeType,
        private int $fileSize,
        private ?int $width,
        private ?int $height,
        private int $sortOrder,
        private ?string $createdAt,
        private ?string $updatedAt,
    ) {}

    /**
     * Vrati stabilni identitu souboru
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Vrati kod vlastniciho modulu
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vrati identitu vlastnici entity
     */
    public function entityId(): int
    {
        return $this->entityId;
    }

    /**
     * Vrati zpusob pouziti souboru u vlastnika
     */
    public function usage(): string
    {
        return $this->usage;
    }

    /**
     * Vrati ulozeny druh souboru
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * Vrati kategorii media odvozenou z MIME typu
     */
    public function mediaCategory(): string
    {
        return $this->mediaCategory;
    }

    /**
     * Vrati identitu image assetu
     */
    public function assetId(): ?string
    {
        return $this->assetId;
    }

    /**
     * Vrati immutable verzi image zdroje
     */
    public function sourceVersion(): ?string
    {
        return $this->sourceVersion;
    }

    /**
     * Vrati relativni cestu generic souboru v upload ulozisti
     */
    public function storagePath(): ?string
    {
        return $this->storagePath;
    }

    /**
     * Vrati puvodni nazev souboru
     */
    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * Vrati volitelny presentation nazev
     */
    public function displayName(): ?string
    {
        return $this->displayName;
    }

    /**
     * Vrati volitelny popisek souboru
     */
    public function caption(): ?string
    {
        return $this->caption;
    }

    /**
     * Vrati priponu souboru
     */
    public function extension(): string
    {
        return $this->extension;
    }

    /**
     * Vrati overeny MIME typ souboru
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Vrati velikost souboru v bajtech
     */
    public function fileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Vrati sirku obrazku v pixelech
     */
    public function width(): ?int
    {
        return $this->width;
    }

    /**
     * Vrati vysku obrazku v pixelech
     */
    public function height(): ?int
    {
        return $this->height;
    }

    /**
     * Vrati poradi souboru v ramci usage
     */
    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Vrati cas vytvoreni databazoveho zaznamu
     */
    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * Vrati cas posledni zmeny databazoveho zaznamu
     */
    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * Vrati legacy pole pro zachovani kontraktu AdminFileModelu
     *
     * @return array{
     *     id:int,
     *     module_code:string,
     *     entity_id:int,
     *     usage:string,
     *     kind:string,
     *     media_category:string,
     *     asset_id:string|null,
     *     source_version:string|null,
     *     storage_path:string|null,
     *     original_filename:string,
     *     display_name:string|null,
     *     caption:string|null,
     *     extension:string,
     *     mime_type:string,
     *     file_size:int,
     *     width:int|null,
     *     height:int|null,
     *     sort_order:int,
     *     created_at:string|null,
     *     updated_at:string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'module_code' => $this->moduleCode,
            'entity_id' => $this->entityId,
            'usage' => $this->usage,
            'kind' => $this->kind,
            'media_category' => $this->mediaCategory,
            'asset_id' => $this->assetId,
            'source_version' => $this->sourceVersion,
            'storage_path' => $this->storagePath,
            'original_filename' => $this->originalFilename,
            'display_name' => $this->displayName,
            'caption' => $this->caption,
            'extension' => $this->extension,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
