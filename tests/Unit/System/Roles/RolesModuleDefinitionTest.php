<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Roles;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\System\Roles\RolesModuleDefinition;
use PHPUnit\Framework\TestCase;

final class RolesModuleDefinitionTest extends TestCase
{
    public function testItDeclaresTheCanonicalCrudPermissionsWithViewDependencies(): void
    {
        $definitions = (new RolesModuleDefinition())->permissionDefinitions();

        self::assertSame([
            'system.roles.view',
            'system.roles.create',
            'system.roles.edit',
            'system.roles.delete',
            'system.roles.restore',
        ], array_map(static fn(PermissionDefinition $definition): string => $definition->code(), $definitions));
        self::assertSame([[], ['system.roles.view'], ['system.roles.view'], ['system.roles.view'], ['system.roles.view']], array_map(static fn(PermissionDefinition $definition): array => $definition->requires(), $definitions));
    }
}
