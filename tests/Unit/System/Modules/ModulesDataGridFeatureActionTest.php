<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Modules;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Feature\ModuleFeatureCategory;
use Lemonade\Admin\Modules\Feature\ModuleFeatureDefinition;
use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestDefinition;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class ModulesDataGridFeatureActionTest extends TestCase
{
    public function testFeatureActionRequiresDiscoveredFeatureDefinitionsAndExcludesOrphans(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        try {
            $database = new Database(new FeatureUiConnection([
                ['code' => 'feature.ui', 'enabled' => 1],
                ['code' => 'feature.empty', 'enabled' => 1],
                ['code' => 'feature.orphan', 'enabled' => 1],
            ]), $this->createMock(DatabaseDriverInterface::class));
            $states = new ModuleStateResolver($catalog, new ModuleModel($database));
            $grid = new ModulesDataGrid(
                $states,
                $this->translator(),
                $this->createMock(ModuleActionPresentationFactory::class),
                $this->urls(),
            );
            $items = array_map(static fn($item): array => $item->toArray(), $grid->execute(new \Lemonade\Admin\DataGrid\Query\DataGridQuery(1, 100, 'code', 'asc', '', []))->items());

            $featureRow = $this->row($items, 'feature.ui');
            $emptyRow = $this->row($items, 'feature.empty');
            $orphanRow = $this->row($items, 'feature.orphan');
            self::assertContains('features', array_column($featureRow['actions'], 'key'));
            self::assertSame('/admin/modules/feature.ui/features', $featureRow['actions'][0]['url']);
            self::assertNotContains('features', array_column($emptyRow['actions'], 'key'));
            self::assertNotContains('features', array_column($orphanRow['actions'], 'key'));
        } finally {
            $cleanup();
        }
    }

    public function testFeatureActionIsShownForDiscoveredFeatureDefinition(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        try {
            $database = new Database(new FeatureUiConnection([
                ['code' => 'feature.ui', 'enabled' => 1],
            ]), $this->createMock(DatabaseDriverInterface::class));
            $states = new ModuleStateResolver($catalog, new ModuleModel($database));
            $grid = new ModulesDataGrid(
                $states,
                $this->translator(),
                $this->createMock(ModuleActionPresentationFactory::class),
                $this->urls(),
            );
            $items = array_map(static fn($item): array => $item->toArray(), $grid->execute(new \Lemonade\Admin\DataGrid\Query\DataGridQuery(1, 100, 'code', 'asc', '', []))->items());

            self::assertContains('features', array_column($this->row($items, 'feature.ui')['actions'], 'key'));
        } finally {
            $cleanup();
        }
    }

    public function testItUsesModuleCodeToDeterministicallyBreakEqualSortValues(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        try {
            $database = new Database(new FeatureUiConnection([
                ['code' => 'feature.ui', 'enabled' => 1],
                ['code' => 'feature.empty', 'enabled' => 1],
            ]), $this->createMock(DatabaseDriverInterface::class));
            $states = new ModuleStateResolver($catalog, new ModuleModel($database));
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('get')->willReturnCallback(static fn(string $key): string => str_ends_with($key, '.name') ? 'Same name' : $key);
            $grid = new ModulesDataGrid(
                $states,
                $translator,
                $this->createMock(ModuleActionPresentationFactory::class),
                $this->urls(),
            );

            $items = $grid->execute(new \Lemonade\Admin\DataGrid\Query\DataGridQuery(1, 100, 'name', 'asc', '', []))->items();

            self::assertSame(['feature.empty', 'feature.ui'], array_map(
                static function ($item): string {
                    $cell = $item->toArray()['cells']['code'];

                    return is_array($cell) ? (string) $cell[0]['value'] : (string) $cell;
                },
                $items,
            ));
        } finally {
            $cleanup();
        }
    }

    /** @param list<array<string, mixed>> $items
     *  @return array<string, mixed>
     */
    private function row(array $items, string $code): array
    {
        foreach ($items as $item) {
            if (($item['cells']['code'] ?? null) === $code) {
                return $item;
            }
        }

        self::fail('Missing DataGrid row: ' . $code);
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        return $translator;
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.modules.features', '/admin/modules/{module}/features', ControllerAction::for('ModulesFeaturesController', 'show'));

        return new UrlGenerator($router);
    }

    /** @return array{ModuleCatalog, callable():void} */
    private function catalog(): array
    {
        $root = sys_get_temp_dir() . '/module-feature-grid-' . bin2hex(random_bytes(8));
        mkdir($root . '/storage/cache', 0775, true);
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        $catalog = new ModuleCatalog($context);
        file_put_contents($catalog->cachePath(), "<?php\nreturn ['" . FeatureUiManifestFixture::class . "', '" . FeatureUiEmptyManifestFixture::class . "'];\n");

        return [$catalog, static function () use ($catalog, $root): void {
            @unlink($catalog->cachePath());
            @rmdir(dirname($catalog->cachePath()));
            @rmdir($root . '/storage');
            @rmdir($root);
        }];
    }
}

final class FeatureUiManifestFixture extends ModuleFeatureManifestDefinition
{
    public function __construct()
    {
        parent::__construct(
            'feature.ui',
            \Lemonade\Admin\Modules\Definition\ModuleKind::Optional,
            FeatureUiProvider::class,
            'feature.ui.name',
            [new ModuleFeatureDefinition('gallery', ModuleFeatureCategory::Toggle, 'feature.gallery')],
        );
    }
}

final class FeatureUiEmptyManifestFixture extends ModuleFeatureManifestDefinition
{
    public function __construct()
    {
        parent::__construct(
            'feature.empty',
            \Lemonade\Admin\Modules\Definition\ModuleKind::Optional,
            FeatureUiProvider::class,
            'feature.empty.name',
        );
    }
}

final class FeatureUiProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
    }
}

final class FeatureUiConnection implements ConnectionInterface
{
    /** @param list<array{code:string,enabled:int}> $moduleRows */
    public function __construct(private readonly array $moduleRows) {}

    /** @return list<array{code:string,enabled:int}> */
    public function select(string $sql, array $bindings = []): array
    {
        unset($bindings);
        if (str_contains($sql, 'system_module_feature')) {
            return [];
        }
        if (str_contains($sql, 'FROM system_module')) {
            return $this->moduleRows;
        }

        return [];
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
