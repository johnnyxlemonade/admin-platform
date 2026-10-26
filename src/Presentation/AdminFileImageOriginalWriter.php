<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use Lemonade\Image\ImageOriginalMetadata;

/**
 * Zapisuje bezpecne finalizovany image upload do canonical original storage
 */
final readonly class AdminFileImageOriginalWriter
{
    /**
     * Nastavuje cteni finalizovaneho uploadu, canonical path resolver a writer
     */
    public function __construct(
        private Filesystem $filesystem,
        private ImageVariantPathResolver $paths,
        private ImageFileWriterInterface $writer,
    ) {}

    /**
     * Zapise jiz validovany a dekodovany Framework image vysledek bez dalsi upload validace
     *
     * @return array{sourceVersion:string,metadata:ImageOriginalMetadata}
     */
    public function write(string $assetId, UploadedImage $uploaded, string $originalFilename): array
    {
        $contents = $this->filesystem->read($uploaded->storedPath());
        if (strlen($contents) !== $uploaded->sizeBytes()) {
            throw new \RuntimeException('Finalized image size changed before canonical publication.');
        }

        $format = ImageFormat::fromMimeType($uploaded->mimeType());
        $dimensions = new ImageDimensions($uploaded->width(), $uploaded->height());
        $encoded = new EncodedImage($contents, $format, $dimensions);
        $sourceVersion = bin2hex(random_bytes(32));
        $original = $this->paths->originalReference(
            $assetId,
            $sourceVersion,
            ImageOriginalStorage::Storage,
            $format,
            $dimensions,
        );
        $this->writer->write(
            $encoded,
            $this->paths->originalPath(new ImageAsset($assetId, $original)),
        );

        return [
            'sourceVersion' => $sourceVersion,
            'metadata' => new ImageOriginalMetadata(
                originalFilename: basename($originalFilename),
                format: $format,
                size: $uploaded->sizeBytes(),
                width: $uploaded->width(),
                height: $uploaded->height(),
            ),
        ];
    }
}
