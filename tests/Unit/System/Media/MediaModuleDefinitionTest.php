<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Media;

use Lemonade\Admin\System\Media\MediaModuleDefinition;
use PHPUnit\Framework\TestCase;

final class MediaModuleDefinitionTest extends TestCase
{
    public function testItDeclaresEditAsAMediaCatalogPermissionThatRequiresView(): void
    {
        $permissions = (new MediaModuleDefinition())->permissionDefinitions();
        $byCode = [];
        foreach ($permissions as $permission) {
            $byCode[$permission->code()] = $permission;
        }

        self::assertArrayHasKey('system.media.view', $byCode);
        self::assertArrayHasKey('system.media.edit', $byCode);
        self::assertSame(['system.media.view'], $byCode['system.media.edit']->requires());
    }
}
