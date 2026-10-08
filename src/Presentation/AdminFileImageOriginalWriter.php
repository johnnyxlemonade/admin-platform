<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadOptions;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use Lemonade\Image\ImageOriginalMetadata;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Validuje image upload a zapisuje jej do Framework canonical original storage
 */
final readonly class AdminFileImageOriginalWriter
{
    /**
     * Nastavuje Framework validaci, encode pipeline, canonical path resolver a writer
     */
    public function __construct(
        private ImageUploadValidator $validator,
        private FileUploadValidator $files,
        private ImageProcessorInterface $processor,
        private ImageEncoderInterface $encoder,
        private ImageVariantPathResolver $paths,
        private ImageFileWriterInterface $writer,
        private UploadConfig $uploadConfig,
    ) {}

    /**
     * Zapise novou immutable source version a vrati metadata pro shared system_file zaznam
     *
     * @return array{sourceVersion:string,metadata:ImageOriginalMetadata}
     */
    public function write(string $assetId, UploadedFileInterface $file, string $profile): array
    {
        $configuration = $this->uploadConfig->images[$profile] ?? null;
        if ($configuration === null) {
            throw new \OutOfBoundsException('Image upload profile is not configured.');
        }

        $this->validator->validate($file, new ImageUploadOptions(
            targetDirectory: sys_get_temp_dir(),
            targetRelativeDirectory: 'tmp',
            maxBytes: $configuration->maxBytes,
            allowedExtensions: $configuration->allowedExtensions,
            reencode: $configuration->reencode,
            minWidth: $configuration->minWidth,
            maxWidth: $configuration->maxWidth,
            minHeight: $configuration->minHeight,
            maxHeight: $configuration->maxHeight,
        ));
        $decoded = $this->processor->decode(ImageSource::fromFile($this->files->resolvePath($file)));
        $format = $decoded->sourceFormat();
        $encoded = $this->encoder->encode($decoded, $format, ImageQuality::fromInt(85));
        $sourceVersion = bin2hex(random_bytes(32));
        $original = $this->paths->originalReference($assetId, $sourceVersion, ImageOriginalStorage::Storage, $format, $decoded->dimensions());
        $this->writer->write($encoded, $this->paths->originalPath(new \Lemonade\Framework\Image\Value\ImageAsset($assetId, $original)));

        return [
            'sourceVersion' => $sourceVersion,
            'metadata' => new ImageOriginalMetadata(
                originalFilename: basename((string) $file->getClientFilename()),
                format: $format,
                size: strlen($encoded->contents()),
                width: $decoded->dimensions()->width,
                height: $decoded->dimensions()->height,
            ),
        ];
    }

    /**
     * Overi zda generic finalizace muze prejit do aktualne podporovane image pipeline
     */
    public function supports(UploadedFile $uploaded, string $originalFilename, string $profile): bool
    {
        $configuration = $this->uploadConfig->images[$profile] ?? null;
        if ($configuration === null) {
            return false;
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, $configuration->allowedExtensions, true) || $uploaded->sizeBytes() > $configuration->maxBytes) {
            return false;
        }

        try {
            \Lemonade\Framework\Image\Value\ImageFormat::fromMimeType($uploaded->mimeType());
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
