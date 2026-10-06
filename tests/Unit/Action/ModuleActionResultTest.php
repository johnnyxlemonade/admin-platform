<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Action;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionResult;
use PHPUnit\Framework\TestCase;

final class ModuleActionResultTest extends TestCase
{
    public function testDefinitionOwnsTheDefaultGridRefreshAndRuntimeCanOverrideIt(): void
    {
        $definition = new ModuleActionDefinition('delete', 'system.roles.delete', 'roles.actions.delete', null, true);

        self::assertTrue(ModuleActionResult::success()->withDefinitionDefaults($definition)->refreshGrid());
        self::assertFalse(ModuleActionResult::success(null, [], false)->withDefinitionDefaults($definition)->refreshGrid());
    }

    public function testActionWithoutConfirmationRemainsRepresentable(): void
    {
        self::assertNull((new ModuleActionDefinition('enable', 'system.languages.enable', 'languages.actions.enable'))->confirmation());
    }

    public function testInformationalActionResultKeepsTheSharedGridRefreshDefault(): void
    {
        $definition = new ModuleActionDefinition('export', 'system.notifications.view', 'notifications.actions.export', null, true);

        self::assertSame('info', ModuleActionResult::info('notifications.actions.bulk_export_pending')->feedbackType());
        self::assertTrue(ModuleActionResult::info('notifications.actions.bulk_export_pending')->withDefinitionDefaults($definition)->refreshGrid());
    }
}
