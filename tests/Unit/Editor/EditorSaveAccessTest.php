<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\ModuleProviderResolver;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\Validation\ValidationSchema;
use PHPUnit\Framework\TestCase;

/**
 * Overuje oddelena prava cteni a mutace sdileneho file editoru
 */
final class EditorSaveAccessTest extends TestCase
{
    /**
     * Ctenar otevre data editoru, ale mutacni file transport nesmi projit
     */
    public function testReadAccessDoesNotGrantSaveAccess(): void
    {
        $container = new Container();
        $provider = new class implements EditorProviderInterface, EditorAccessPolicyInterface {
            public function editorDefinition(): EditorDefinition
            {
                return new EditorDefinition('system.users.view', 'system.users.edit', 'system.users.create', []);
            }

            public function loadAccessDecision(int $id): bool
            {
                return true;
            }

            public function saveAccessDecision(int $id): bool
            {
                return false;
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

            public function create(array $data): EditorSaveResult
            {
                throw new \LogicException('Not used.');
            }

            public function update(int $id, array $data): EditorSaveResult
            {
                throw new \LogicException('Not used.');
            }
        };
        $container->instance('media-test-editor', $provider);
        $container->freeze();
        $modules = new AdminModuleRegistry(new AdminNavigationGroupRegistry());
        $modules->register(new UsersModuleDefinition());
        $editors = new EditorRegistry();
        $editors->register('system.users', 'media-test-editor');
        $resolver = new ModuleProviderResolver(
            new DataGridRegistry(),
            $editors,
            new ModulePageRegistry(),
            new ModuleActionRegistry(),
            $container,
        );
        $dispatcher = new EditorDispatcher(
            $modules,
            $resolver,
            (new \ReflectionClass(AuthorizationService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(FormValidation::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(EditorLockManager::class))->newInstanceWithoutConstructor(),
        );
        self::assertSame(['id' => 7], $dispatcher->updateData('system.users', 7)->data());
        $this->expectException(EditorCapabilityException::class);
        $dispatcher->assertSaveAccess('system.users', 7);
    }
}
