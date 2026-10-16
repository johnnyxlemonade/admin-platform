<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Module\ModuleProviderResolver;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Validation\ValidationSchema;
use PHPUnit\Framework\TestCase;

/**
 * Overuje requestove vlastnictvi modulovych runtime provideru
 */
final class ModuleProviderResolverTest extends TestCase
{
    /**
     * Overuje canonical identitu v requestu a izolaci mezi requesty
     */
    public function testProvidersAreMaterializedAndCachedOnlyInCurrentRequestScope(): void
    {
        $dataGrids = new DataGridRegistry();
        $editors = new EditorRegistry();
        $pages = new ModulePageRegistry();
        $actions = new ModuleActionRegistry();
        $dataGrids->register('test.module', ScopedModuleProviderStub::class);
        $editors->register('test.module', ScopedModuleProviderStub::class);
        $pages->registerIndex('test.module', ScopedPageProviderStub::class);
        $pages->registerEditor('test.module', ScopedPageProviderStub::class);
        $pages->registerModalEditor('test.module', ScopedPageProviderStub::class);
        $actions->register('test.module', new ModuleActionDefinition('save', 'test.edit', 'test.save'), ScopedActionHandlerStub::class);

        $container = new Container();
        $container->instance(DataGridRegistry::class, $dataGrids);
        $container->instance(EditorRegistry::class, $editors);
        $container->instance(ModulePageRegistry::class, $pages);
        $container->instance(ModuleActionRegistry::class, $actions);
        $container->scoped(ModuleProviderResolver::class, ModuleProviderResolver::class);
        $container->freeze();

        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);
        $firstResolver = $firstScope->get(ModuleProviderResolver::class);
        $secondResolver = $secondScope->get(ModuleProviderResolver::class);
        $firstProvider = $firstResolver->dataGrid('test.module');

        self::assertSame($firstProvider, $firstResolver->editor('test.module'));
        $firstPageProvider = $firstResolver->index('test.module');
        self::assertSame($firstPageProvider, $firstResolver->editorPage('test.module'));
        self::assertSame($firstPageProvider, $firstResolver->modalEditor('test.module'));
        self::assertNotSame($firstPageProvider, $secondResolver->index('test.module'));
        self::assertSame(
            $firstResolver->action('test.module', 'save')['handler'],
            $firstResolver->action('test.module', 'save')['handler'],
        );
        self::assertNotSame(
            $firstResolver->action('test.module', 'save')['handler'],
            $secondResolver->action('test.module', 'save')['handler'],
        );
        self::assertNotSame($firstProvider, $secondResolver->dataGrid('test.module'));
        self::assertSame(ScopedModuleProviderStub::class, $dataGrids->providerServiceId('test.module'));
        self::assertSame(ScopedModuleProviderStub::class, $editors->providerServiceId('test.module'));

        $firstScope->close();
        $secondScope->close();
    }
}

/**
 * Poskytuje vsechny modulove runtime contracty pro scope test
 */
final class ScopedModuleProviderStub implements DataGridProviderInterface, EditorProviderInterface
{
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition('test.module', [], false, [], 'id', 'asc', 20, 100);
    }

    public function permission(): string
    {
        return 'test.view';
    }

    public function execute(DataGridQuery $query): DataGridResult
    {
        return new DataGridResult([], 1, 20, 0);
    }

    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition('test.view', 'test.edit', 'test.create', []);
    }

    public function updateData(int $id): array
    {
        return ['id' => $id];
    }

    public function createData(): array
    {
        return [];
    }

    public function createValidationSchema(): ValidationSchema
    {
        return ValidationSchema::create();
    }

    public function updateValidationSchema(int $id): ValidationSchema
    {
        return ValidationSchema::create();
    }

    public function update(int $id, array $data): EditorSaveResult
    {
        return new EditorSaveResult([], 'test.saved');
    }

    public function create(array $data): EditorSaveResult
    {
        return new EditorSaveResult([], 'test.created');
    }
}

/**
 * Poskytuje page contracty pro scope test
 */
final class ScopedPageProviderStub implements ModuleIndexPageProviderInterface, ModuleEditorPageProviderInterface, ModuleModalEditorPageProviderInterface
{
    public function indexPermission(): string
    {
        return 'test.view';
    }

    public function index(string $locale): ModulePage
    {
        return new ModulePage('test::index', 'Test', []);
    }

    public function create(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return new ModulePage('test::create', 'Test', []);
    }

    public function editor(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
    {
        return new ModulePage('test::editor', 'Test', []);
    }

    public function modalCreate(EditorLoaded $editor, string $locale): ModulePage
    {
        return new ModulePage('test::modal-create', 'Test', []);
    }

    public function modalEdit(EditorLoaded $editor, string $locale): ModulePage
    {
        return new ModulePage('test::modal-edit', 'Test', []);
    }
}

/**
 * Poskytuje action handler pro scope test
 */
final class ScopedActionHandlerStub implements ModuleActionHandlerInterface
{
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        return ModuleActionResult::success();
    }
}
