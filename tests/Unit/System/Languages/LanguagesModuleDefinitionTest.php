<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use PHPUnit\Framework\TestCase;

final class LanguagesModuleDefinitionTest extends TestCase
{
    public function testItProvidesTheSystemLanguagesCapabilitiesWithoutDelete(): void
    {
        $definition = new LanguagesModuleDefinition();
        $metadata = $definition->adminMetadata();

        self::assertSame('system.languages', $definition->code());
        self::assertSame('languages', $metadata->routeSegment());
        self::assertSame('system', $metadata->navigationGroup());
        self::assertSame(55, $metadata->navigationOrder());
        self::assertSame(AdminIcon::Sliders, $metadata->icon());
        self::assertSame([
            'system.languages.view',
            'system.languages.create',
            'system.languages.edit',
            'system.languages.enable',
            'system.languages.disable',
            'system.languages.set_default',
        ], array_map(static fn(\Lemonade\Admin\Authorization\PermissionDefinition $permission): string => $permission->code(), $definition->permissionDefinitions()));
    }
}
