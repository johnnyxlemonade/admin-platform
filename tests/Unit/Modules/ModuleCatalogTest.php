<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Catalog\ModuleManifestDiscovery;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleCatalogTest extends TestCase
{
    public function testComposerAutoloadLifecycleDoesNotRunApplicationCommands(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $scripts = $composer['scripts'] ?? [];

        self::assertArrayNotHasKey('post-autoload-dump', $scripts);
        self::assertStringNotContainsString('database:migrate', json_encode($scripts, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('modules:migrate', json_encode($scripts, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('permissions:sync', json_encode($scripts, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('development:seed', json_encode($scripts, JSON_THROW_ON_ERROR));
    }

    public function testItRejectsAnIncompleteComposerInstallation(): void
    {
        $temporary = sys_get_temp_dir() . '/lemonade-module-catalog-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0775, true);
        $catalog = new ModuleCatalog((new ApplicationContextFactory())->create($temporary, ['APP_ENV' => 'testing']));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Composer installed package metadata');
            $this->expectExceptionMessage('Run Composer install to complete the application installation.');
            $catalog->all();
        } finally {
            @rmdir($temporary);
        }
    }

    public function testItValidatesTheBuiltinManifestCatalogDeterministically(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $classes = $composer['extra']['lemonade']['modules'] ?? [];
        self::assertIsArray($classes);

        $modules = $this->discoverComposerModules([[
            'name' => 'johnnyxlemonade/admin-platform',
            'extra' => ['lemonade' => ['modules' => array_values($classes)]],
        ]]);

        self::assertSame(
            ['system.audit', 'system.languages', 'system.media', 'system.modules', 'system.notifications', 'system.roles', 'system.translations', 'system.users'],
            array_map(static fn($module): string => $module->code(), $modules),
        );
    }

    public function testItDiscoversComposerDeclaredManifest(): void
    {
        $modules = $this->discoverComposerModules([
            [
                'name' => 'vendor/cms-news',
                'extra' => [
                    'lemonade' => [
                        'modules' => [ComposerNewsManifestFixture::class],
                    ],
                ],
            ],
        ]);

        self::assertSame(['cms.news'], array_map(static fn($module): string => $module->code(), $modules));
        self::assertSame(ComposerPackageProviderFixture::class, $modules[0]->manifest()->runtimeProvider());
        self::assertSame('vendor/cms-news', $modules[0]->packageName());
        self::assertSame('dev-main', $modules[0]->version());
    }

    /**
     * Composer manifest je dostupny primo z canonical package metadata
     */
    public function testItLoadsComposerManifestFromInstalledMetadata(): void
    {
        $temporary = sys_get_temp_dir() . '/lemonade-module-runtime-' . bin2hex(random_bytes(8));
        mkdir($temporary . '/vendor/composer', 0775, true);
        file_put_contents($temporary . '/vendor/composer/installed.json', json_encode(['packages' => [[
            'name' => 'vendor/cms-news',
            'version' => '1.2.3',
            'description' => 'News module',
            'extra' => ['lemonade' => ['modules' => [ComposerNewsManifestFixture::class]]],
        ]]], JSON_THROW_ON_ERROR));

        try {
            $catalog = new ModuleCatalog((new ApplicationContextFactory())->create($temporary, ['APP_ENV' => 'testing']));

            self::assertSame(['cms.news'], array_map(static fn($module): string => $module->code(), $catalog->all()));
            self::assertTrue($catalog->has('cms.news'));
            self::assertFalse($catalog->has('cms.page'));
            self::assertSame('1.2.3', $catalog->all()[0]->version());
            self::assertSame('News module', $catalog->all()[0]->description());
            self::assertFileDoesNotExist($temporary . '/storage/cache/modules.php');
        } finally {
            @unlink($temporary . '/vendor/composer/installed.json');
            @rmdir($temporary . '/vendor/composer');
            @rmdir($temporary . '/vendor');
            @rmdir($temporary);
        }
    }

    public function testItRejectsDuplicateCodesDeclaredByComposerPackages(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Module code "cms.news" must be unique and non-empty.');

        $this->discoverComposerModules([
            [
                'name' => 'vendor/cms-news',
                'extra' => [
                    'lemonade' => [
                        'modules' => [ComposerNewsManifestFixture::class],
                    ],
                ],
            ],
            [
                'name' => 'vendor/cms-news-duplicate',
                'extra' => [
                    'lemonade' => [
                        'modules' => [ComposerDuplicateNewsManifestFixture::class],
                    ],
                ],
            ],
        ]);
    }

    public function testItRejectsInvalidComposerManifestClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Module manifest "%s" must implement', \stdClass::class));

        $this->discoverComposerModules([
            [
                'name' => 'vendor/cms-invalid',
                'extra' => [
                    'lemonade' => [
                        'modules' => [\stdClass::class],
                    ],
                ],
            ],
        ]);
    }

    public function testItIgnoresComposerPackageWithoutModuleMetadata(): void
    {
        self::assertSame([], $this->discoverComposerModules([
            [
                'name' => 'vendor/without-modules',
                'extra' => [
                    'other' => ['value' => 'ignored'],
                ],
            ],
        ]));
    }

    public function testItBuildsComposerCatalogInModuleCodeOrder(): void
    {
        $modules = $this->discoverComposerModules([
            [
                'name' => 'vendor/cms-zeta',
                'extra' => [
                    'lemonade' => [
                        'modules' => [ComposerZetaManifestFixture::class],
                    ],
                ],
            ],
            [
                'name' => 'vendor/cms-alpha',
                'extra' => [
                    'lemonade' => [
                        'modules' => [ComposerAlphaManifestFixture::class],
                    ],
                ],
            ],
        ]);

        self::assertSame(['cms.alpha', 'cms.zeta'], array_map(static fn($module): string => $module->code(), $modules));
    }

    /**
     * @param list<array<string, mixed>> $packages
     * @return list<\Lemonade\Admin\Modules\Catalog\AvailableModule>
     */
    private function discoverComposerModules(array $packages): array
    {
        $temporary = sys_get_temp_dir() . '/lemonade-composer-modules-' . bin2hex(random_bytes(8));
        $metadataDirectory = $temporary . '/vendor/composer';
        mkdir($metadataDirectory, 0775, true);
        $metadataPath = $metadataDirectory . '/installed.json';
        $packages = array_map(
            static fn(array $package): array => ['version' => 'dev-main', 'description' => '', ...$package],
            $packages,
        );
        file_put_contents($metadataPath, json_encode(['packages' => $packages], JSON_THROW_ON_ERROR));

        try {
            $context = (new ApplicationContextFactory())->create($temporary, ['APP_ENV' => 'testing']);

            return (new ModuleManifestDiscovery($context))->discover();
        } finally {
            @unlink($metadataPath);
            @rmdir($metadataDirectory);
            @rmdir(dirname($metadataDirectory));
            @rmdir($temporary);
        }
    }
}

final class ComposerPackageProviderFixture implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
    }
}

final class ComposerNewsManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('cms.news', ComposerPackageProviderFixture::class);
    }
}

final class ComposerDuplicateNewsManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('cms.news', ComposerPackageProviderFixture::class);
    }
}

final class ComposerAlphaManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('cms.alpha', ComposerPackageProviderFixture::class);
    }
}

final class ComposerZetaManifestFixture extends ModuleManifestDefinition
{
    public function __construct()
    {
        parent::__construct('cms.zeta', ComposerPackageProviderFixture::class);
    }
}
