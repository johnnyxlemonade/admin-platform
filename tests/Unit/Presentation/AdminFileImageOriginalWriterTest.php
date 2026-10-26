<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\AdminFileImageOriginalWriter;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\FilesystemImageFileWriter;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\ValueObject\UploadedImage;
use PHPUnit\Framework\TestCase;

/**
 * Overuje canonical zapis jiz finalizovaneho Framework image uploadu
 */
final class AdminFileImageOriginalWriterTest extends TestCase
{
    private string $root = '';

    /**
     * Pripravi oddeleny public a storage root pro fyzicky image zapis
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-admin-image-original-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/public/uploads/admin/images', 0775, true);
        mkdir($this->root . '/storage', 0775, true);
    }

    /**
     * Odstrani pouze izolovany testovaci filesystem
     */
    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    /**
     * Overuje zachovani validovanych bajtu, rozmeru, MIME a puvodniho nazvu bez revalidace
     */
    public function testItPublishesFinalizedImageAsCanonicalOriginal(): void
    {
        $sourcePath = $this->root . '/public/uploads/admin/images/finalized.png';
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        unset($image);
        self::assertIsString($contents);
        file_put_contents($sourcePath, $contents);
        $uploaded = new UploadedImage(
            storedFilename: 'finalized.png',
            storedPath: $sourcePath,
            storedRelativePath: 'uploads/admin/images/finalized.png',
            mimeType: 'image/png',
            sizeBytes: strlen($contents),
            width: 1,
            height: 1,
        );
        [$writer, $paths] = $this->writer();

        $written = $writer->write('asset-123', $uploaded, '../hero.png');
        $metadata = $written['metadata'];
        $original = $paths->originalReference(
            'asset-123',
            $written['sourceVersion'],
            ImageOriginalStorage::Storage,
            ImageFormat::Png,
            new ImageDimensions(1, 1),
        );
        $canonicalPath = $paths->originalPath(new ImageAsset('asset-123', $original));

        self::assertSame($contents, file_get_contents($canonicalPath));
        self::assertSame('image/png', (new MimeTypeDetector())->detect($canonicalPath)->value());
        self::assertSame('hero.png', $metadata->originalFilename());
        self::assertSame('image/png', $metadata->format()->mimeType());
        self::assertSame(strlen($contents), $metadata->size());
        self::assertSame(1, $metadata->width());
        self::assertSame(1, $metadata->height());
        self::assertFileExists($sourcePath);
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0644, fileperms($canonicalPath) & 0777);
            self::assertSame(0755, fileperms(dirname($canonicalPath)) & 0777);
        }
    }

    /**
     * Overuje odmitnuti souboru zmeneneho po Framework finalizaci
     */
    public function testItRejectsChangedFinalizedImageSize(): void
    {
        $sourcePath = $this->root . '/public/uploads/admin/images/changed.png';
        file_put_contents($sourcePath, 'changed');
        $uploaded = new UploadedImage(
            storedFilename: 'changed.png',
            storedPath: $sourcePath,
            storedRelativePath: 'uploads/admin/images/changed.png',
            mimeType: 'image/png',
            sizeBytes: 999,
            width: 10,
            height: 10,
        );
        [$writer] = $this->writer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Finalized image size changed');

        $writer->write('asset-123', $uploaded, 'changed.png');
    }

    /**
     * Vytvori writer nad izolovanym skutecnym filesystemem
     *
     * @return array{AdminFileImageOriginalWriter,ImageVariantPathResolver}
     */
    private function writer(): array
    {
        $filesystem = new Filesystem(
            new DirectoryManager(),
            new FileManager(),
            new LockManager(new DirectoryManager()),
        );
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
            DebugMode::disabled(),
        );
        $paths = new ImageVariantPathResolver($context, new DirectoryPathGenerator());

        return [
            new AdminFileImageOriginalWriter(
                $filesystem,
                $paths,
                new FilesystemImageFileWriter($filesystem),
            ),
            $paths,
        ];
    }

    /**
     * Rekurzivne odstrani pouze testem vytvoreny adresar
     */
    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            throw new \RuntimeException(sprintf('Test directory "%s" could not be read.', $path));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->remove($path . '/' . $entry);
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    }
}
