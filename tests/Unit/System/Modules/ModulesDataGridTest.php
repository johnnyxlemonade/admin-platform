<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Modules;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
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
use PHPUnit\Framework\TestCase;

/**
 * Overuje deterministicke razeni management prehledu modulu
 */
final class ModulesDataGridTest extends TestCase
{
    /**
     * Pouziva module code jako tie-breaker shodnych lokalizovanych nazvu
     */
    public function testUsesModuleCodeToDeterministicallyBreakEqualSortValues(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        try {
            $database = new Database(
                new ModulesDataGridConnection([
                    ['code' => 'test.second', 'enabled' => 1],
                    ['code' => 'test.first', 'enabled' => 1],
                ]),
                $this->createMock(DatabaseDriverInterface::class),
            );
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('get')->willReturnCallback(
                static fn(string $key): string => str_ends_with($key, '.name') ? 'Same name' : $key,
            );
            $grid = new ModulesDataGrid(
                new ModuleStateResolver($catalog, new ModuleModel($database)),
                $translator,
                $this->getMockBuilder(ModuleActionPresentationFactory::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
            );

            $items = $grid->execute(new DataGridQuery(1, 100, 'name', 'asc', '', []))->items();

            self::assertSame(['test.first', 'test.second'], array_map(
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

    /**
     * Vytvori izolovany discovery katalog pro DataGrid test
     *
     * @return array{ModuleCatalog, callable():void}
     */
    private function catalog(): array
    {
        $root = sys_get_temp_dir() . '/module-grid-' . bin2hex(random_bytes(8));
        $metadataDirectory = $root . '/vendor/composer';
        mkdir($metadataDirectory, 0775, true);
        $metadataPath = $metadataDirectory . '/installed.json';
        file_put_contents($metadataPath, json_encode(['packages' => [[
            'name' => 'lemonade/module-grid-test-modules',
            'extra' => ['lemonade' => ['modules' => [FirstModuleManifestFixture::class, SecondModuleManifestFixture::class]]],
        ]]], JSON_THROW_ON_ERROR));
        $context = (new ApplicationContextFactory())->create($root, ['APP_ENV' => 'testing']);
        $catalog = new ModuleCatalog($context);

        return [$catalog, static function () use ($metadataPath, $metadataDirectory, $root): void {
            @unlink($metadataPath);
            @rmdir($metadataDirectory);
            @rmdir($root . '/vendor');
            @rmdir($root);
        }];
    }
}

/**
 * Deklaruje prvni optional modul pro stabilni razeni
 */
final class FirstModuleManifestFixture extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod a shodny lokalizacni klic prvniho modulu
     */
    public function __construct()
    {
        parent::__construct('test.first', ModuleKind::Optional, ModulesDataGridProviderFixture::class, 'test.first.name');
    }
}

/**
 * Deklaruje druhy optional modul pro stabilni razeni
 */
final class SecondModuleManifestFixture extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod a shodny lokalizacni klic druheho modulu
     */
    public function __construct()
    {
        parent::__construct('test.second', ModuleKind::Optional, ModulesDataGridProviderFixture::class, 'test.second.name');
    }
}

/**
 * Poskytuje validni runtime provider testovacich manifestu
 */
final class ModulesDataGridProviderFixture implements ServiceProviderInterface
{
    /**
     * Nepridava zadne runtime contributions
     */
    public function register(ContainerBuilderInterface $container): void
    {
        unset($container);
    }
}

/**
 * Vraci pouze lifecycle radky potrebne pro DataGrid projection
 */
final readonly class ModulesDataGridConnection implements ConnectionInterface
{
    /**
     * Nastavuje lifecycle radky testovacich modulu
     *
     * @param list<array{code:string,enabled:int}> $moduleRows
     */
    public function __construct(private array $moduleRows) {}

    /**
     * Vraci lifecycle snapshot bez skutecne databaze
     *
     * @return list<array{code:string,enabled:int}>
     */
    public function select(string $sql, array $bindings = []): array
    {
        unset($sql, $bindings);

        return $this->moduleRows;
    }

    /**
     * Nevraci zadne streamovane radky
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        unset($sql, $bindings);
        yield from [];
    }

    /**
     * Nepodporuje mutace v read-only testu
     */
    public function statement(string $sql, array $bindings = []): int
    {
        unset($sql, $bindings);

        return 0;
    }

    /**
     * Neotevira transakci v read-only testu
     */
    public function beginTransaction(): void {}

    /**
     * Nepotvrzuje transakci v read-only testu
     */
    public function commit(): void {}

    /**
     * Nevraci transakci zpet v read-only testu
     */
    public function rollBack(): void {}

    /**
     * Oznamuje absenci aktivni transakce
     */
    public function inTransaction(): bool
    {
        return false;
    }

    /**
     * Spusti callback bez databazove transakce
     */
    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Nevraci insert identitu v read-only testu
     */
    public function lastInsertId(): int|string|null
    {
        return null;
    }

    /**
     * Nehlasi ovlivnene radky v read-only testu
     */
    public function affectedRows(): int
    {
        return 0;
    }

    /**
     * Neprovadi reconnect v read-only testu
     */
    public function reconnect(): void {}

    /**
     * Nezavira externi spojeni v read-only testu
     */
    public function close(): void {}

    /**
     * Vraci prazdnou verzi testovaciho serveru
     */
    public function serverVersion(): string
    {
        return '';
    }

    /**
     * Vraci vstup bez databazoveho escapovani
     */
    public function escapeString(string $value): string
    {
        return $value;
    }
}
