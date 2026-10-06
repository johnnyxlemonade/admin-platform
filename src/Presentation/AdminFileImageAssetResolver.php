<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Image\Contract\ImageAssetResolverInterface;
use Lemonade\Image\ImageIdentifier;

/**
 * Sestavuje Framework image asset pouze z aktivniho shared system_file zaznamu
 */
final readonly class AdminFileImageAssetResolver implements ImageAssetResolverInterface
{
    /**
     * Nastavuje shared file metadata a Framework canonical original path resolver
     */
    public function __construct(private AdminFileModel $files, private ImageVariantPathResolver $paths) {}

    /**
     * Vraci image asset jen pro aktivni image soubor odpovidajici modulu a public file ID
     */
    public function resolve(string $module, ImageIdentifier $identifier): ?ImageAsset
    {
        $file = $this->files->findActiveImage($module, (int) $identifier->value());
        if ($file === null || !is_string($file['asset_id']) || !is_string($file['source_version']) || $file['width'] === null || $file['height'] === null) {
            return null;
        }

        try {
            $format = ImageFormat::fromMimeType((string) $file['mime_type']);
            $dimensions = new ImageDimensions((int) $file['width'], (int) $file['height']);
            $original = $this->paths->originalReference($file['asset_id'], $file['source_version'], ImageOriginalStorage::Storage, $format, $dimensions);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return new ImageAsset($file['asset_id'], $original);
    }
}
