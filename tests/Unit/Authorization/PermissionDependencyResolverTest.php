<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PermissionDependencyResolverTest extends TestCase
{
    public function testItResolvesTransitivePrerequisitesWithoutDuplicates(): void
    {
        $resolver = $this->resolver([
            new PermissionDefinition('test.c', 'test', 'test.c'),
            new PermissionDefinition('test.b', 'test', 'test.b', requires: ['test.c']),
            new PermissionDefinition('test.a', 'test', 'test.a', requires: ['test.b', 'test.c']),
        ]);

        self::assertSame(['test.c', 'test.b', 'test.a'], $resolver->withPrerequisites(['test.a', 'test.b']));
    }

    public function testStateClosureRemovesDependentsOfAnExplicitlyDeniedPrerequisite(): void
    {
        $resolver = $this->resolver([
            new PermissionDefinition('test.view', 'test', 'test.view'),
            new PermissionDefinition('test.edit', 'test', 'test.edit', requires: ['test.view']),
        ]);

        self::assertSame(
            ['test.view' => false],
            $resolver->withPrerequisitesInStates(['test.view' => false, 'test.edit' => true]),
        );
    }

    public function testItRejectsDependencyCycles(): void
    {
        $resolver = $this->resolver([
            new PermissionDefinition('test.a', 'test', 'test.a', requires: ['test.b']),
            new PermissionDefinition('test.b', 'test', 'test.b', requires: ['test.a']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Permission dependency cycle:');
        $resolver->validateCatalog();
    }

    public function testItRejectsUnknownPrerequisites(): void
    {
        $resolver = $this->resolver([
            new PermissionDefinition('test.a', 'test', 'test.a', requires: ['test.missing']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test.missing');
        $resolver->validateCatalog();
    }

    public function testCurrentCrudCatalogDeclaresOnlyExplicitViewPrerequisites(): void
    {
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(...(new LanguagesModuleDefinition())->permissionDefinitions());
        $catalog->register(...(new UsersModuleDefinition())->permissionDefinitions());
        $resolver = new PermissionDependencyResolver($catalog);

        self::assertSame(['system.languages.view'], $resolver->requirementsFor('system.languages.create'));
        self::assertSame(['system.users.view'], $resolver->requirementsFor('system.users.manage_permissions'));
        self::assertSame([], $resolver->requirementsFor('system.users.view'));
    }

    /** @param list<PermissionDefinition> $definitions */
    private function resolver(array $definitions): PermissionDependencyResolver
    {
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(...$definitions);

        return new PermissionDependencyResolver($catalog);
    }
}
