<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Presentation\AdminFileMutationService;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\ImageVariantCache;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Image\ImageOriginalMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Overuje fyzicky cleanup jedne immutable image source version
 */
final class AdminFileMutationServiceTest extends TestCase
{
    private string $root = '';

    /**
     * Pripravi izolovany storage a public upload root
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-admin-file-mutation-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/public', 0700, true);
        mkdir($this->root . '/storage', 0700, true);
    }

    /**
     * Odstrani pouze testem vytvoreny filesystem
     */
    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->root);
    }

    /**
     * Overuje rollback cleanup originalu a variant bez zasahu do jine source version
     */
    public function testDiscardRemovesOriginalAndOnlyMatchingVariants(): void
    {
        [$service, $filesystem, $paths, $variants] = $this->service();
        $removed = $this->asset($paths, 'version-one');
        $retained = $this->asset($paths, 'version-two');
        $definition = new ImageVariantDefinition(
            new ImageDimensions(64, 64),
            ImageFormat::Webp,
            ImageQuality::fromInt(80),
        );
        $filesystem->write($paths->originalPath($removed), 'original-one', 0644);
        $filesystem->write($paths->originalPath($retained), 'original-two', 0644);
        foreach ([$removed, $retained] as $asset) {
            $variants->remember(
                $asset,
                $definition,
                static fn(): EncodedImage => new EncodedImage(
                    'variant',
                    ImageFormat::Webp,
                    new ImageDimensions(64, 64),
                ),
            );
        }

        $service->discardImageOriginal(
            'asset-123',
            'version-one',
            new ImageOriginalMetadata('image.png', ImageFormat::Png, 12, 1, 1),
        );

        self::assertFileDoesNotExist($paths->originalPath($removed));
        self::assertDirectoryDoesNotExist($paths->variantDirectory($removed));
        self::assertFileExists($paths->originalPath($retained));
        self::assertFileExists($paths->variantPath($retained, $definition));
    }

    /**
     * Sestavi mutation service s realnym image filesystemem a nepouzitymi DB zavislostmi
     *
     * @return array{AdminFileMutationService,Filesystem,ImageVariantPathResolver,ImageVariantCache}
     */
    private function service(): array
    {
        $directory = new DirectoryManager();
        $filesystem = new Filesystem($directory, new FileManager(), new LockManager($directory));
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
            DebugMode::disabled(),
        );
        $paths = new ImageVariantPathResolver($context, new DirectoryPathGenerator());
        $variants = new ImageVariantCache($filesystem, $paths);

        return [
            new AdminFileMutationService(
                $this->withoutConstructor(AdminFileModel::class),
                $this->withoutConstructor(TransactionalEventProcessor::class),
                $this->withoutConstructor(LocalActorGuard::class),
                $paths,
                $variants,
                $filesystem,
                $context,
                $this->withoutConstructor(Database::class),
            ),
            $filesystem,
            $paths,
            $variants,
        ];
    }

    /**
     * Vytvori bezpecnou image referenci pro jednu testovaci source version
     */
    private function asset(ImageVariantPathResolver $paths, string $sourceVersion): ImageAsset
    {
        return new ImageAsset(
            'asset-123',
            $paths->originalReference(
                'asset-123',
                $sourceVersion,
                ImageOriginalStorage::Storage,
                ImageFormat::Png,
                new ImageDimensions(1, 1),
            ),
        );
    }

    /**
     * Vytvori nepouzitou zavislost bez spusteni jeji infrastruktury
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function withoutConstructor(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
