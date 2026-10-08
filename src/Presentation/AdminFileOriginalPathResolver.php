<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;

/**
 * Prevadi ulozena system_file metadata na canonical original path
 */
final readonly class AdminFileOriginalPathResolver
{
    /**
     * Nastavuje Framework resolvery image a generic upload storage
     */
    public function __construct(
        private ImageVariantPathResolver $images,
        private ApplicationContext $context,
    ) {}

    /**
     * Vrati original path jen pro uplna a platna image nebo generic file metadata
     *
     * @param array<string,mixed> $metadata
     */
    public function pathFor(array $metadata): ?string
    {
        if (($metadata['kind'] ?? null) === 'file') {
            $storagePath = $metadata['storage_path'] ?? null;

            return is_string($storagePath) && $storagePath !== '' && !str_starts_with($storagePath, '/')
                ? $this->context->uploadPath($storagePath)
                : null;
        }
        if (($metadata['kind'] ?? null) !== 'image') {
            return null;
        }
        try {
            $assetId = (string) ($metadata['asset_id'] ?? '');
            $sourceVersion = (string) ($metadata['source_version'] ?? '');
            if ($assetId === '' || $sourceVersion === '' || $metadata['width'] === null || $metadata['height'] === null) {
                return null;
            }
            $original = $this->images->originalReference($assetId, $sourceVersion, ImageOriginalStorage::Storage, ImageFormat::fromMimeType((string) $metadata['mime_type']), new ImageDimensions((int) $metadata['width'], (int) $metadata['height']));

            return $this->images->originalPath(new ImageAsset($assetId, $original));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
