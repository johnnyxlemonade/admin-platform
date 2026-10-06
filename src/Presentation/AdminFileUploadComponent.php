<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Upload\Config\UploadConfig;

/**
 * Vytvari shared editorovou presentation uploadu podle modulove image identity
 */
final readonly class AdminFileUploadComponent
{
    /**
     * Nastavuje source lookup, thumbnail presentation a named route generator
     */
    public function __construct(
        private AdminFileModel $files,
        private AdminThumbnailComponent $thumbnails,
        private UrlGenerator $urls,
        private AdminFileUsageRegistry $usages,
        private UploadConfig $uploadConfig,
    ) {}

    /**
     * Vytvori collection contract nad registrovanou shared usage bez moduloveho transportu
     */
    public function collection(
        string $module,
        int $entityId,
        string $usage,
        string $labelKey,
        string $helpKey,
        bool $editable = true,
        ?string $fallback = null,
        string $alt = '',
    ): AdminFileUploadCollection {
        $definition = $this->usages->require($module, $usage);
        $placeholder = '__upload_id__';

        return new AdminFileUploadCollection(
            moduleCode: $module,
            entityId: $entityId,
            usage: $usage,
            files: $this->collectionState($module, $entityId, $usage),
            startUrl: $this->urls->route('admin.file.chunk.start', ['module' => $module, 'usage' => $usage, 'entity' => $entityId]),
            appendUrlTemplate: $this->urls->route('admin.file.chunk.append', ['uploadId' => $placeholder]),
            completeUrlTemplate: $this->urls->route('admin.file.chunk.complete', ['uploadId' => $placeholder]),
            abortUrlTemplate: $this->urls->route('admin.file.chunk.abort', ['uploadId' => $placeholder]),
            removeUrlTemplate: $this->urls->route('admin.file.collection.remove', ['module' => $module, 'usage' => $usage, 'entity' => $entityId, 'file' => $placeholder]),
            reorderUrl: $this->urls->route('admin.file.collection.reorder', ['module' => $module, 'usage' => $usage, 'entity' => $entityId]),
            renameModalUrlTemplate: $this->urls->route('admin.file.collection.rename.modal', ['module' => $module, 'usage' => $usage, 'entity' => $entityId, 'file' => $placeholder]),
            labelKey: $labelKey,
            helpKey: $helpKey,
            kind: $definition->kind(),
            multiple: $definition->multiple(),
            sortable: $definition->sortable(),
            editable: $editable,
            profile: $this->profilePresentation($definition),
            presentation: $definition->presentation(),
            fallback: $fallback,
            alt: $alt,
        );
    }

    /**
     * Vraci aktualni shared kolekci po samostatne AJAX mutaci.
     *
     * @return list<array<string,mixed>>
     */
    public function collectionState(string $module, int $entityId, string $usage): array
    {
        $files = $this->files->listForEntity($module, $entityId, $usage);
        foreach ($files as &$file) {
            $file['metadata'] = $this->fileMetadata($file);

            if ($file['kind'] === 'image' && is_string($file['source_version'] ?? null)) {
                $definition = $this->usages->require($module, $usage);
                $presentation = $definition->presentation() === AdminFileUploadPresentation::Landscape ? 'preview' : 'thumbnail';
                $url = $this->thumbnails->image($module, (string) $file['id'], presentation: $presentation)->url();
                $file['preview_url'] = $url === null ? null : $this->versionedUrl($url, $file['source_version']);
            }
        }
        unset($file);

        return $files;
    }

    /**
     * Pridava immutable source version k canonical image URL pro okamzite nacteni replacementu
     */
    private function versionedUrl(string $url, string $sourceVersion): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($sourceVersion);
    }

    /**
     * Prevadi authoritativni framework profil na data spotrebovavana pouze shared view
     */
    private function profilePresentation(AdminFileUsageDefinition $definition): AdminFileUploadProfilePresentation
    {
        $profile = $definition->kind() === 'image'
            ? $this->uploadConfig->images[$definition->profile()] ?? null
            : $this->uploadConfig->files[$definition->profile()] ?? null;
        if ($profile === null) {
            throw new \OutOfBoundsException('Upload profile is not configured.');
        }

        return new AdminFileUploadProfilePresentation(
            accept: implode(',', array_map(static fn(string $extension): string => '.' . $extension, $profile->allowedExtensions)),
            allowedExtensions: array_map(strtoupper(...), $profile->allowedExtensions),
            maxBytes: $profile->maxBytes,
            maxSizeLabel: human_filesize($profile->maxBytes, 1),
        );
    }

    /**
     * Sestavuje metadata uz ulozeneho shared souboru pro upload collection view.
     *
     * @param array<string,mixed> $file
     */
    private function fileMetadata(array $file): string
    {
        $metadata = [strtoupper((string) $file['extension'])];
        $width = filter_var($file['width'] ?? null, FILTER_VALIDATE_INT);
        $height = filter_var($file['height'] ?? null, FILTER_VALIDATE_INT);

        if ($file['kind'] === 'image' && is_int($width) && $width > 0 && is_int($height) && $height > 0) {
            $metadata[] = sprintf('%d × %d px', $width, $height);
        }

        $metadata[] = human_filesize((int) $file['file_size'], 1);

        return implode(' · ', $metadata);
    }
}
