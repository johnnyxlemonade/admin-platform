<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\AdminFileImageOriginalWriter;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Image\Contract\ImageEncoderInterface;
use Lemonade\Framework\Image\Contract\ImageFileWriterInterface;
use Lemonade\Framework\Image\Contract\ImageProcessorInterface;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetectorInterface;
use Lemonade\Framework\Upload\Config\ImageUploadProfileConfig;
use Lemonade\Framework\Upload\Config\UploadConfig;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Overuje rozhodnuti o prevodu generic souboru do image pipeline
 */
final class AdminFileImageOriginalWriterTest extends TestCase
{
    /**
     * Overi ze image bind pouzije jen MIME a profil podporovane runtime
     */
    #[DataProvider('conversionCandidates')]
    public function testRecognizesOnlyImageProfileAndRuntimeSupportedFormats(string $filename, string $mimeType, int $size, bool $expected): void
    {
        self::assertSame($expected, $this->writer()->supports(
            new UploadedFile('upload', '/tmp/upload', 'uploads/admin/files/upload', $mimeType, $size),
            $filename,
            'admin-image',
        ));
    }

    /**
     * Vrati soubory pro polymorfni attachment finalizaci
     *
     * @return iterable<string, array{string, string, int, bool}>
     */
    public static function conversionCandidates(): iterable
    {
        yield 'JPEG attachment converts to image' => ['attachment.jpg', 'image/jpeg', 1_024, true];
        yield 'PNG attachment converts to image' => ['attachment.png', 'image/png', 1_024, true];
        yield 'WebP attachment converts to image' => ['attachment.webp', 'image/webp', 1_024, true];
        yield 'PDF attachment remains generic' => ['attachment.pdf', 'application/pdf', 1_024, false];
        yield 'TXT attachment remains generic' => ['attachment.txt', 'text/plain', 1_024, false];
        yield 'GIF remains generic because runtime cannot encode it' => ['attachment.gif', 'image/gif', 1_024, false];
        yield 'oversized image remains generic because image profile rejects it' => ['attachment.jpg', 'image/jpeg', 5_242_881, false];
    }

    /**
     * Vytvori writer s aktualnim admin image profilem bez filesystem operace
     */
    private function writer(): AdminFileImageOriginalWriter
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $files = new FileUploadValidator(
            $translator,
            $this->createMock(MimeTypeDetectorInterface::class),
            MimeTypeCatalog::default(),
        );

        return new AdminFileImageOriginalWriter(
            new ImageUploadValidator($files, $translator),
            $files,
            $this->createMock(ImageProcessorInterface::class),
            $this->createMock(ImageEncoderInterface::class),
            new ImageVariantPathResolver(
                new ApplicationContext(Environment::Testing, new Path('/test', '/test/public'), DebugMode::disabled()),
                new DirectoryPathGenerator(),
            ),
            $this->createMock(ImageFileWriterInterface::class),
            new UploadConfig(
                files: [],
                images: [
                    'admin-image' => new ImageUploadProfileConfig(
                        targetDirectory: '/test/uploads/admin/images',
                        maxBytes: 5_242_880,
                        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
                        reencode: true,
                        minWidth: null,
                        maxWidth: null,
                        minHeight: null,
                        maxHeight: null,
                    ),
                ],
            ),
        );
    }
}
