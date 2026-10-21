<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Modules;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje read-only Composer prehled modulu
 */
final class ModulesDataGridTest extends TestCase
{
    /**
     * Zobrazuje package metadata a pouziva kod jako stabilni tie-breaker
     */
    public function testItProjectsComposerMetadataWithoutLifecycleActions(): void
    {
        [$catalog, $cleanup] = $this->catalog();
        try {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('get')->willReturnCallback(
                static fn(string $key): string => str_ends_with($key, '.name') ? 'Same name' : $key,
            );
            $grid = new ModulesDataGrid($catalog, $translator);

            $items = $grid->execute(new DataGridQuery(1, 100, 'name', 'asc', '', []))->items();
            $rows = array_map(static fn($item): array => $item->toArray(), $items);

            self::assertSame(['test.first', 'test.second'], array_map(
                static function (array $row): string {
                    $code = $row['cells']['code'];
                    self::assertIsString($code);

                    return $code;
                },
                $rows,
            ));
            self::assertSame('1.2.3', $rows[0]['cells']['version']);
            self::assertSame('Test modules', $rows[0]['cells']['description']);
            self::assertSame([], $rows[0]['actions']);
            self::assertSame(['name', 'code', 'version', 'description'], array_map(
                static fn($column): string => $column->key(),
                $grid->dataGridDefinition()->columns(),
            ));
        } finally {
            $cleanup();
        }
    }

    /**
     * Vytvori izolovany Composer katalog pro DataGrid test
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
            'version' => '1.2.3',
            'description' => 'Test modules',
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
 * Deklaruje prvni modul pro stabilni razeni
 */
final class FirstModuleManifestFixture extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod a shodny lokalizacni klic prvniho modulu
     */
    public function __construct()
    {
        parent::__construct('test.first', ModulesDataGridProviderFixture::class, 'test.first.name');
    }
}

/**
 * Deklaruje druhy modul pro stabilni razeni
 */
final class SecondModuleManifestFixture extends ModuleManifestDefinition
{
    /**
     * Nastavuje kod a shodny lokalizacni klic druheho modulu
     */
    public function __construct()
    {
        parent::__construct('test.second', ModulesDataGridProviderFixture::class, 'test.second.name');
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
    public function register(ContainerBuilderInterface $container): void {}
}
