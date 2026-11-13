<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\File\FileReadModel;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Image\Contract\ImageAssetResolverInterface;

/**
 * Builds a Framework image asset from active shared file metadata
 */
final readonly class AdminFileImageAssetResolver implements ImageAssetResolverInterface
{
    /**
     * Connects shared file metadata and the canonical original path resolver
     */
    public function __construct(private FileReadModel $files, private ImageVariantPathResolver $paths) {}

    /**
     * Reads image metadata and its owner by global file ID
     */
    public function resolve(int $fileId): ?ImageAsset
    {
        $file = $this->files->findFile($fileId, 'image');
        if ($file === null || $file->assetId() === null || $file->sourceVersion() === null || $file->width() === null || $file->height() === null) {
            return null;
        }

        try {
            $format = ImageFormat::fromMimeType($file->mimeType());
            $dimensions = new ImageDimensions($file->width(), $file->height());
            $original = $this->paths->originalReference(
                assetId: $file->assetId(),
                sourceVersion: $file->sourceVersion(),
                format: $format,
                dimensions: $dimensions,
            );
        } catch (\InvalidArgumentException) {
            return null;
        }

        return new ImageAsset($file->assetId(), $original);
    }
}
