<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Modules;

use InvalidArgumentException;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Catalog\ModuleManifestDiscovery;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Feature\FeatureProviderRegistry;
use Lemonade\Admin\Modules\Feature\ModuleFeatureCategory;
use Lemonade\Admin\Modules\Feature\ModuleFeatureDefinition;
use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestDefinition;
use Lemonade\Admin\Modules\Feature\ModuleFeatureStateResolver;
use Lemonade\Admin\Modules\Persistence\ModuleFeatureModel;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\System\Modules\ModulesFeaturesPageProvider;
use Lemonade\Admin\System\Modules\ViewModels\ModuleFeaturesPageViewModel;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleFeatureStateResolverTest extends TestCase
{
    public function testItReadsSupportedFeaturesFromADiscoveredButUninstalledModule(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection([], []);
        try {
            $manager = $this->manager($catalog, $connection);
            $state = $manager->feature('feature.optional', 'gallery');

            self::assertTrue($manager->featureSupported('feature.optional', 'gallery'));
            self::assertNotNull($state);
            self::assertFalse($state->installed());
            self::assertFalse($state->enabled());
        } finally {
            $cleanup();
        }
    }

    public function testItKeepsConfiguredFeatureStateWhenTheModuleIsDisabled(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => false],
            [['code' => 'feature.optional', 'feature' => 'gallery', 'enabled' => 1, 'provider' => null]],
        );
        try {
            $manager = $this->manager($catalog, $connection);
            $state = $manager->feature('feature.optional', 'gallery');

            self::assertNotNull($state);
            self::assertTrue($state->installed());
            self::assertFalse($state->moduleEnabled());
            self::assertTrue($state->configuredEnabled());
            self::assertFalse($manager->featureEnabled('feature.optional', 'gallery'));
        } finally {
            $cleanup();
        }
    }

    public function testRequiredCapabilityNeedsNoDatabaseRow(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(['feature.optional' => true], []);
        try {
            $manager = $this->manager($catalog, $connection);
            $state = $manager->feature('feature.optional', 'editor');

            self::assertNotNull($state);
            self::assertTrue($state->configuredEnabled());
            self::assertSame('feature.editor', $state->definition()->labelKey());
            self::assertTrue($manager->featureEnabled('feature.optional', 'editor'));
        } finally {
            $cleanup();
        }
    }

    public function testOptionalToggleUsesItsPersistedState(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => true],
            [['code' => 'feature.optional', 'feature' => 'gallery', 'enabled' => 0, 'provider' => null]],
        );
        try {
            $manager = $this->manager($catalog, $connection);

            self::assertFalse($manager->featureEnabled('feature.optional', 'gallery'));
        } finally {
            $cleanup();
        }
    }

    public function testMissingDatabaseRowUsesManifestDefault(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(['feature.optional' => true], []);
        try {
            $manager = $this->manager($catalog, $connection);

            self::assertTrue($manager->featureEnabled('feature.optional', 'gallery'));
        } finally {
            $cleanup();
        }
    }

    public function testStaleDatabaseFeatureIsNotSupported(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => true],
            [['code' => 'feature.optional', 'feature' => 'removed', 'enabled' => 1, 'provider' => null]],
        );
        try {
            $manager = $this->manager($catalog, $connection);

            self::assertFalse($manager->featureSupported('feature.optional', 'removed'));
            self::assertNull($manager->feature('feature.optional', 'removed'));
        } finally {
            $cleanup();
        }
    }

    public function testInvalidProviderPreventsFeatureProviderResolution(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => true],
            [['code' => 'feature.optional', 'feature' => 'attachments', 'enabled' => 1, 'provider' => 'missing']],
        );
        try {
            $manager = $this->manager($catalog, $connection);
            $state = $manager->feature('feature.optional', 'attachments');

            self::assertNotNull($state);
            self::assertFalse($state->providerValid());
            self::assertFalse($manager->featureEnabled('feature.optional', 'attachments'));
            $this->expectException(\Lemonade\Admin\Modules\Feature\ModuleConfigurationException::class);
            $manager->featureProvider('feature.optional', 'attachments');
        } finally {
            $cleanup();
        }
    }

    public function testFeatureRowsAreLoadedWithOneBulkRead(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => true],
            [
                ['code' => 'feature.optional', 'feature' => 'gallery', 'enabled' => 1, 'provider' => null],
                ['code' => 'feature.optional', 'feature' => 'attachments', 'enabled' => 1, 'provider' => 'local'],
            ],
        );
        try {
            $manager = $this->manager($catalog, $connection);

            $manager->feature('feature.optional', 'gallery');
            $manager->feature('feature.optional', 'attachments');
            $manager->feature('feature.optional', 'editor');

            self::assertSame('local', $manager->featureProvider('feature.optional', 'attachments'));
            self::assertSame(1, $connection->featureSelects);
            self::assertSame(1, $connection->moduleSelects);
        } finally {
            $cleanup();
        }
    }

    public function testProviderIsIgnoredWhenTheFeatureDeclaresNoProviders(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => true],
            [['code' => 'feature.optional', 'feature' => 'gallery', 'enabled' => 1, 'provider' => 'unknown']],
        );
        try {
            $manager = $this->manager($catalog, $connection);

            self::assertTrue($manager->featureEnabled('feature.optional', 'gallery'));
            self::assertNull($manager->featureProvider('feature.optional', 'gallery'));
        } finally {
            $cleanup();
        }
    }

    public function testFeaturePageUsesTypedViewModelsAndKeepsConfiguredStateOnDisabledModule(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection(
            ['feature.optional' => false],
            [['code' => 'feature.optional', 'feature' => 'gallery', 'enabled' => 1, 'provider' => null]],
        );
        $translator = $this->createMock(\Lemonade\Framework\Localization\TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);
        try {
            $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
            $states = new ModuleStateResolver($catalog, new ModuleModel($database));
            $featureStates = new ModuleFeatureStateResolver($states, new ModuleFeatureModel($database), new FeatureProviderRegistry());
            $page = (new ModulesFeaturesPageProvider($states, $featureStates, $translator, $this->urls()))->page('feature.optional', 'cs', true);

            self::assertNotNull($page);
            $view = $page->data()['page'] ?? null;
            self::assertInstanceOf(ModuleFeaturesPageViewModel::class, $view);
            self::assertSame('modules.state.disabled', $view->lifecycleStateLabel());
            self::assertCount(4, $view->features());
            self::assertNull($view->features()[0]->action());
            self::assertNotNull($view->features()[2]->action());
            self::assertSame('/admin/system/modules/ajax/' . abs(crc32('feature.optional')), $view->features()[2]->action()->url());
            self::assertSame('modules.features.states.enabled', $view->features()[2]->configuredStateLabel());
            self::assertSame('modules.features.states.disabled', $view->features()[2]->effectiveStateLabel());
        } finally {
            $cleanup();
        }
    }

    public function testFeaturePageShowsUninstalledDeclarationsWithoutMutationControls(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        $connection = new FeatureStateConnection([], []);
        $translator = $this->createMock(\Lemonade\Framework\Localization\TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);
        try {
            $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
            $states = new ModuleStateResolver($catalog, new ModuleModel($database));
            $featureStates = new ModuleFeatureStateResolver($states, new ModuleFeatureModel($database), new FeatureProviderRegistry());
            $provider = new ModulesFeaturesPageProvider($states, $featureStates, $translator, $this->urls());
            $page = $provider->page('feature.optional', 'cs', true);
            $unknown = $provider->page('feature.unknown', 'cs', true);

            self::assertNotNull($page);
            self::assertNull($unknown);
            $view = $page->data()['page'] ?? null;
            self::assertInstanceOf(ModuleFeaturesPageViewModel::class, $view);
            self::assertSame('modules.state.available', $view->lifecycleStateLabel());
            foreach ($view->features() as $feature) {
                self::assertNull($feature->action());
            }
        } finally {
            $cleanup();
        }
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->postNamed('admin.module.ajax.entity', '/admin/{module}/ajax/{id}', ControllerAction::for('ModuleActionController', 'entity'));
        $router->postNamed('admin.system.module.ajax.entity', '/admin/system/{module}/ajax/{id}', ControllerAction::for('ModuleActionController', 'entity'));

        return new UrlGenerator($router);
    }

    public function testItRejectsDuplicateFeatureCodesInOneManifest(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicated');

        ModuleManifestDiscovery::manifestsFromClasses([DuplicateFeatureManifestFixture::class]);
    }

    public function testItRejectsInvalidCapabilityDefaults(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleFeatureDefinition('editor', ModuleFeatureCategory::AdminCapability, 'feature.editor', true, false);
    }

    public function testItRejectsRequiredToggleFeatures(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleFeatureDefinition('gallery', ModuleFeatureCategory::Toggle, 'feature.gallery', true, true);
    }

    public function testItRejectsInvalidProviderCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuleFeatureDefinition('attachments', ModuleFeatureCategory::Toggle, 'feature.attachments', false, true, ['NextCloud']);
    }

    /** @return array{ModuleCatalog, callable():void} */
    private function catalog(): array
    {
        $root = sys_get_temp_dir() . '/module-feature-state-' . bin2hex(random_bytes(8));
        mkdir($root . '/storage/cache', 0775, true);
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        $catalog = new ModuleCatalog($context);
        file_put_contents($catalog->cachePath(), "<?php\nreturn ['" . FeatureOptionalManifestFixture::class . "'];\n");

        return [$catalog, static function () use ($catalog, $root): void {
            @unlink($catalog->cachePath());
            @rmdir(dirname($catalog->cachePath()));
            @rmdir($root . '/storage');
            @rmdir($root);
        }];
    }

    private function manager(ModuleCatalog $catalog, FeatureStateConnection $connection): ModuleManager
    {
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $states = new ModuleStateResolver($catalog, new ModuleModel($database));
        $features = new ModuleFeatureModel($database);
        $featureStates = new ModuleFeatureStateResolver($states, $features, new FeatureProviderRegistry());

        return new ModuleManager(new FeatureProviderRegistry(), $states, $features, $featureStates);
    }
}

final class FeatureOptionalManifestFixture extends ModuleFeatureManifestDefinition
{
    public function __construct()
    {
        parent::__construct(
            'feature.optional',
            ModuleKind::Optional,
            FeatureFixtureProvider::class,
            'feature.optional.name',
            [
                new ModuleFeatureDefinition('editor', ModuleFeatureCategory::AdminCapability, 'feature.editor', true, true),
                new ModuleFeatureDefinition('language_versions', ModuleFeatureCategory::StructuralCapability, 'feature.language_versions', true, true),
                new ModuleFeatureDefinition('gallery', ModuleFeatureCategory::Toggle, 'feature.gallery', false, true),
                new ModuleFeatureDefinition('attachments', ModuleFeatureCategory::Toggle, 'feature.attachments', false, true, ['local']),
            ],
        );
    }
}

final class DuplicateFeatureManifestFixture extends ModuleFeatureManifestDefinition
{
    public function __construct()
    {
        parent::__construct(
            'feature.duplicate',
            ModuleKind::Optional,
            FeatureFixtureProvider::class,
            'feature.duplicate.name',
            [
                new ModuleFeatureDefinition('gallery', ModuleFeatureCategory::Toggle, 'feature.gallery'),
                new ModuleFeatureDefinition('gallery', ModuleFeatureCategory::Toggle, 'feature.gallery'),
            ],
        );
    }
}

final class FeatureFixtureProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
    }
}

final class FeatureStateConnection implements ConnectionInterface
{
    public int $moduleSelects = 0;
    public int $featureSelects = 0;

    /**
     * @param array<string, bool> $moduleStates
     * @param list<array{code:string,feature:string,enabled:int,provider:?string}> $featureRows
     */
    public function __construct(private readonly array $moduleStates, private readonly array $featureRows) {}

    public function select(string $sql, array $bindings = []): array
    {
        unset($bindings);
        if (str_contains($sql, 'system_module_feature')) {
            $this->featureSelects++;

            return $this->featureRows;
        }

        $this->moduleSelects++;

        return array_map(static fn(string $code, bool $enabled): array => ['code' => $code, 'enabled' => $enabled ? 1 : 0], array_keys($this->moduleStates), $this->moduleStates);
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        unset($sql, $bindings);

        return 0;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return '';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
