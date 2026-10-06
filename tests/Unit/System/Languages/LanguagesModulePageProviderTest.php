<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\DataGridIndexViewModel;
use Lemonade\Admin\System\Languages\DataGrid\LanguagesDataGrid;
use Lemonade\Admin\System\Languages\Editor\LanguagesAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Languages\LanguagesModulePageProvider;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class LanguagesModulePageProviderTest extends TestCase
{
    public function testCreatePrimaryActionUsesExplicitModalMetadata(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);
        $urls = $this->urls();
        $authorization = $this->authorization();
        $grid = new LanguagesDataGrid(
            languages: (new \ReflectionClass(LanguageModel::class))->newInstanceWithoutConstructor(),
            translator: $translator,
            authorization: $authorization,
            actionPresentation: $this->createMock(ModuleActionPresentationFactory::class),
            urls: $urls,
        );
        $provider = new LanguagesModulePageProvider(
            grid: $grid,
            translator: $translator,
            authorization: $authorization,
            urls: $urls,
            editorDefinitions: new LanguagesAdminEditorDefinitionFactory($urls),
        );

        $page = $provider->index('cs');
        $dataGrid = $page->data()['dataGrid'];

        self::assertInstanceOf(DataGridIndexViewModel::class, $dataGrid);
        $primaryAction = $dataGrid->primaryAction();
        self::assertNotNull($primaryAction);
        self::assertSame('/admin/api/modal/languages/create', $primaryAction->modalUrl());
        self::assertSame('medium', $primaryAction->modalSize());
    }

    private function authorization(): AuthorizationService
    {
        $user = new AuthenticatedUser(7, 'admin@example.test');
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn($user);
        $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn([['id' => 1]]);

        return new AuthorizationService(
            currentUser: $principals,
            database: new Database(
                $connection,
                $this->createMock(DatabaseDriverInterface::class),
            ),
        );
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.api.modal.create', '/admin/api/modal/{module}/create', ControllerAction::for('EditorModalController', 'create'));
        $router->getNamed('admin.api.datagrid.index', '/admin/api/datagrid/{module}', ControllerAction::for('DataGridController', 'index'));

        return new UrlGenerator(router: $router);
    }
}
