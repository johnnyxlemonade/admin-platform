<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Translations;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\System\Translations\DataGrid\TranslationsDataGrid;
use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Localization\TranslationSourceCatalogInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Overuje modalni editaci a canonical selection contract gridu prekladu
 */
final class TranslationsDataGridActionsContractTest extends TestCase
{
    /**
     * Overuje, ze editace je dostupna i bez vlastni hodnoty a reset jen s ni
     */
    public function testRowActionsRespectOverrideStateAndEditPermission(): void
    {
        $default = $this->row(overridden: false, canEdit: true);
        self::assertSame(['edit'], array_column($default['actions'], 'key'));
        self::assertSame('modal', $default['actions'][0]['kind']);
        self::assertSame('pencil-square', $default['actions'][0]['icon']);
        self::assertSame('/admin/api/modal/translations/15/edit', $default['actions'][0]['modalUrl']);
        self::assertSame([[
            'type' => 'link',
            'value' => 'module.name',
            'url' => '#',
            'modalUrl' => '/admin/api/modal/translations/15/edit',
            'modalSize' => 'large',
        ]], $default['cells']['key']);

        $overridden = $this->row(overridden: true, canEdit: true);
        self::assertSame(['edit', 'reset'], array_column($overridden['actions'], 'key'));
        self::assertSame('mutation', $overridden['actions'][1]['kind']);
        self::assertSame('translations.confirm.reset', $overridden['actions'][1]['confirm']);

        $viewOnly = $this->row(overridden: true, canEdit: false);
        self::assertSame([], $viewOnly['actions']);
        self::assertSame('module.name', $viewOnly['cells']['key']);
    }

    /**
     * Overuje, ze hromadna akce aktivuje standardni vyber jen pro editujiciho uzivatele
     */
    public function testBulkResetEnablesCanonicalSelectionOnlyWithEditPermission(): void
    {
        $editableGrid = $this->grid(canEdit: true);
        $definition = $editableGrid->dataGridDefinition();
        $bulkActions = $definition->bulkActions();

        self::assertTrue($definition->selectionEnabled());
        $actionsColumn = array_values(array_filter(
            $definition->columns(),
            static fn($column): bool => $column->key() === 'actions',
        ));
        self::assertCount(1, $actionsColumn);
        self::assertNull($actionsColumn[0]->sortKey());
        $keyColumn = array_values(array_filter(
            $definition->columns(),
            static fn($column): bool => $column->key() === 'key',
        ));
        self::assertCount(1, $keyColumn);
        self::assertSame('key', $keyColumn[0]->sortKey());
        self::assertCount(1, $bulkActions);
        self::assertSame('bulk-reset', $bulkActions[0]->action());
        self::assertSame('/admin/system/translations/ajax', $bulkActions[0]->endpoint());
        self::assertSame(['all', 'default', 'overridden', 'missing'], $bulkActions[0]->allowedViews());
        self::assertSame('translations.confirm.bulk_reset', $bulkActions[0]->confirmation()?->messageKey());

        self::assertFalse($this->grid(canEdit: false)->dataGridDefinition()->selectionEnabled());
    }

    /**
     * Vraci serializovany radek s action presentation odpovidajici permission
     *
     * @return array<string,mixed>
     */
    private function row(bool $overridden, bool $canEdit): array
    {
        $grid = $this->grid($canEdit);
        $method = new ReflectionMethod($grid, 'row');
        $row = $method->invoke($grid, [
            'id' => 15,
            'locale' => 'cs',
            'owner' => 'system.translations',
            'group' => 'translations',
            'key' => 'module.name',
            'source' => 'Překlady',
            'effective' => 'Překlady',
            'overrideValue' => $overridden ? 'Vlastní překlady' : null,
            'overridden' => $overridden,
            'missingSource' => false,
        ], [
            'default' => 'Výchozí',
            'overridden' => 'Upraveno',
            'missing' => 'Chybí překlad',
        ]);

        self::assertInstanceOf(DataGridRowDefinition::class, $row);

        return $row->toArray();
    }

    /**
     * Sestavuje grid s minimalnim source katalogem a daneho opravnenim
     */
    private function grid(bool $canEdit): TranslationsDataGrid
    {
        $sources = $this->createMock(TranslationSourceCatalogInterface::class);
        $sources->method('entries')->willReturn([]);
        $overrides = $this->createMock(TranslationOverrideProviderInterface::class);
        $overrides->method('group')->willReturn([]);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);
        $translator->method('group')->willReturn([
            'status.default' => 'Výchozí',
            'status.overridden' => 'Upraveno',
            'status.missing' => 'Chybí překlad',
        ]);

        return new TranslationsDataGrid(
            catalog: new TranslationsCatalog($sources, $translator, $overrides),
            config: new LocalizationConfig('cs', 'cs', ['cs', 'en'], new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false)),
            translator: $translator,
            authorization: $this->authorization($canEdit),
            actionPresentation: $this->actionPresentation($canEdit),
            urls: $this->urls(),
        );
    }

    /**
     * Sestavuje autorizaci lokalniho uzivatele s volitelnym pravem editace
     */
    private function authorization(bool $canEdit): AuthorizationService
    {
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn($canEdit ? [['id' => 1]] : []);
        if ($canEdit) {
            $user = new AuthenticatedUser(7, 'admin@example.test');
            $principals->method('currentUser')->willReturn($user);
            $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        } else {
            $principals->method('currentUser')->willReturn(null);
            $principals->method('currentPrincipal')->willReturn(null);
        }

        return new AuthorizationService(
            currentUser: $principals,
            database: new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    /**
     * Sestavuje row reset action jen pro uzivatele s pravem editace
     */
    private function actionPresentation(bool $canEdit): ModuleActionPresentationFactory
    {
        $presentation = $this->createMock(ModuleActionPresentationFactory::class);
        $presentation->method('rowAction')->willReturnCallback(static function (
            string $moduleCode,
            string $action,
            int $entityId,
            DataGridRowActionPlacement $placement,
        ) use ($canEdit): ?DataGridRowActionDefinition {
            if (!$canEdit || $moduleCode !== 'system.translations' || $action !== 'reset') {
                return null;
            }

            return new DataGridRowActionDefinition(
                key: 'reset',
                label: 'Reset',
                url: '/admin/translations/ajax/' . $entityId,
                method: 'POST',
                confirmation: new \Lemonade\Admin\Action\Presentation\ConfirmationDefinition('translations.confirm.reset'),
                kind: DataGridRowActionKind::Mutation,
                placement: $placement,
                refresh: true,
            );
        });

        return $presentation;
    }

    /**
     * Sestavuje URL generator pro modalni a hromadny action transport
     */
    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.api.modal.edit', '/admin/api/modal/{module}/{id}/edit', ControllerAction::for('EditorModalController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('ModuleActionController', 'create'));

        return new UrlGenerator($router);
    }
}
