<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;

/**
 * Resolves stored file metadata to the single private original location
 */
final readonly class AdminFileOriginalPathResolver
{
    /**
     * Connects the Framework image resolver and application storage roots
     */
    public function __construct(
        private ImageVariantPathResolver $images,
        private ApplicationContext $context,
    ) {}

    /**
     * Resolves complete image or generic file metadata to its canonical original path
     *
     * @param array<string,mixed> $metadata
     */
    public function pathFor(array $metadata): ?string
    {
        if (($metadata['kind'] ?? null) === 'file') {
            $storagePath = $metadata['storage_path'] ?? null;

            if (!is_string($storagePath) || $storagePath === '' || str_starts_with($storagePath, '/')) {
                return null;
            }

            return $this->context->uploadPath($storagePath);
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
            $original = $this->images->originalReference(
                assetId: $assetId,
                sourceVersion: $sourceVersion,
                format: ImageFormat::fromMimeType((string) $metadata['mime_type']),
                dimensions: new ImageDimensions((int) $metadata['width'], (int) $metadata['height']),
            );

            return $this->images->originalPath(new ImageAsset($assetId, $original));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
