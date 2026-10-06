<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Assets;

use DateTimeImmutable;
use Lemonade\Admin\Assets\AdminAssetConfiguration;
use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Assets\AdminAssetPublisher;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Overuje publikaci package-owned Admin distribuce do izolovaneho host public rootu
 */
final class AdminAssetPublisherTest extends TestCase
{
    /**
     * Distribuuje production-ready manifest pro Admin, Auth, Installer a vendor entry
     */
    public function testPackageDistributionContainsTheRequiredProductionEntries(): void
    {
        $manifestPath = AdminAssetPublisher::distributionPath() . '/.vite/manifest.json';

        self::assertFileExists($manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        $entries = array_filter($manifest, static fn(mixed $chunk): bool => is_array($chunk) && ($chunk['isEntry'] ?? false) === true);
        $names = array_map(static fn(array $chunk): string => (string) $chunk['name'], $entries);

        self::assertContains('admin', $names);
        self::assertContains('auth', $names);
        self::assertContains('installer', $names);
        self::assertContains('vendor', $names);
    }

    /**
     * Prenese manifest a assety, odstrani stale soubory a nezasahne Frontend destination
     */
    public function testItPublishesTheDistributionAndKeepsFrontendAssetsUntouched(): void
    {
        $root = $this->temporaryDirectory();
        $distribution = $root . '/package-dist';
        $public = $root . '/public';
        mkdir($distribution . '/.vite', 0775, true);
        mkdir($distribution . '/assets', 0775, true);
        mkdir($public . '/assets/admin', 0775, true);
        mkdir($public . '/assets/frontend', 0775, true);
        file_put_contents($distribution . '/assets/admin.js', 'admin');
        file_put_contents($distribution . '/assets/admin.css', 'css');
        file_put_contents($distribution . '/assets/auth.js', 'auth');
        file_put_contents($distribution . '/assets/installer.js', 'installer');
        file_put_contents($distribution . '/assets/vendor.css', 'vendor');
        file_put_contents($distribution . '/.vite/manifest.json', json_encode([
            'admin' => [
                'file' => 'assets/admin.js',
                'css' => ['assets/admin.css'],
                'isEntry' => true,
                'name' => 'admin',
            ],
            'auth' => [
                'file' => 'assets/auth.js',
                'isEntry' => true,
                'name' => 'auth',
            ],
            'installer' => [
                'file' => 'assets/installer.js',
                'isEntry' => true,
                'name' => 'installer',
            ],
            'vendor' => [
                'file' => 'assets/vendor.css',
                'isEntry' => true,
                'name' => 'vendor',
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($public . '/assets/admin/stale.js', 'stale');
        file_put_contents($public . '/assets/frontend/keep.js', 'frontend');

        $configuration = new AdminAssetConfiguration('/assets/admin/', 'assets/admin');
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        (new AdminAssetPublisher($context, $configuration, $distribution))->publish();

        self::assertFileExists($public . '/assets/admin/.vite/manifest.json');
        self::assertFileExists($public . '/assets/admin/assets/admin.js');
        self::assertFileDoesNotExist($public . '/assets/admin/stale.js');
        self::assertFileExists($public . '/assets/frontend/keep.js');
        $manifest = new AdminAssetManifest($context, $configuration);
        self::assertSame('/assets/admin/assets/admin.js', $manifest->script('admin'));
        self::assertSame('/assets/admin/assets/auth.js', $manifest->script('auth'));
        self::assertSame('/assets/admin/assets/installer.js', $manifest->script('installer'));
        self::assertSame(['/assets/admin/assets/vendor.css'], $manifest->styles('vendor'));
        self::assertSame('/assets/admin/js/core/theme.js?v=2026-W39', $manifest->staticUrl('js/core/theme.js', new DateTimeImmutable('2026-09-22T12:00:00+00:00')));
    }

    /**
     * Odmitne chybejici package manifest pred zasahem do host public destination
     */
    public function testItRejectsADistributionWithoutManifest(): void
    {
        $root = $this->temporaryDirectory();
        $distribution = $root . '/package-dist';
        mkdir($distribution, 0775, true);
        $configuration = new AdminAssetConfiguration('/assets/admin/', 'assets/admin');
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('distribution manifest is missing');

        (new AdminAssetPublisher($context, $configuration, $distribution))->publish();
    }

    /**
     * Zachovava hostem zvolenou public URL bez zavislosti na default hostu
     */
    public function testItUsesTheConfiguredPublicBaseUrl(): void
    {
        $configuration = new AdminAssetConfiguration('/backoffice-assets/', 'static/admin');

        self::assertSame('/backoffice-assets/js/core/theme.js', $configuration->publicUrl('js/core/theme.js'));
        self::assertSame('static/admin', $configuration->publicDirectory);
    }

    /**
     * Degraduje renderovani bez assetu, zatimco installer muze stav vyhodnotit jako chybu
     */
    public function testItReturnsNoEntriesWhenThePublishedManifestIsMissing(): void
    {
        $root = $this->temporaryDirectory();
        $configuration = new AdminAssetConfiguration('/assets/admin/', 'assets/admin');
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        $manifest = new AdminAssetManifest($context, $configuration);

        self::assertSame('', $manifest->script('admin'));
        self::assertSame([], $manifest->styles('vendor'));
    }

    /**
     * Vytvari izolovany filesystem root pro DB-free publishing contract
     */
    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/lemonade-admin-assets-' . bin2hex(random_bytes(8));
        mkdir($directory, 0775, true);

        return $directory;
    }
}
