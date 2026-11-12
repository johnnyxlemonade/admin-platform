<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\File\FileReadModel;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Image\Contract\ImageAssetResolverInterface;

/**
 * Sestavuje Framework image asset pouze z aktivniho shared system_file zaznamu
 */
final readonly class AdminFileImageAssetResolver implements ImageAssetResolverInterface
{
    /**
     * Nastavuje shared file metadata a Framework canonical original path resolver
     */
    public function __construct(private FileReadModel $files, private ImageVariantPathResolver $paths) {}

    /**
     * Nacte image metadata vcetne vlastniciho modulu podle globalniho ID souboru
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
            $original = $this->paths->originalReference($file->assetId(), $file->sourceVersion(), ImageOriginalStorage::Storage, $format, $dimensions);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return new ImageAsset($file->assetId(), $original);
    }
}
