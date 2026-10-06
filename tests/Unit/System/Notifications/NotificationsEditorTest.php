<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\System\Notifications\Editor\NotificationsEditor;
use Lemonade\Admin\System\Notifications\NotificationsModuleDefinition;
use PHPUnit\Framework\TestCase;

final class NotificationsEditorTest extends TestCase
{
    public function testPublishedNotificationsDeclareTheSamePermissionForCreateAndEdit(): void
    {
        $editor = (new \ReflectionClass(NotificationsEditor::class))->newInstanceWithoutConstructor();

        self::assertSame('system.notifications.publish', $editor->editorDefinition()->createPermission());
        self::assertSame('system.notifications.publish', $editor->editorDefinition()->loadPermission());
        self::assertSame('system.notifications.publish', $editor->editorDefinition()->savePermission());
    }

    public function testResetDisplayPermissionDependsOnNotificationViewPermission(): void
    {
        $permissions = (new NotificationsModuleDefinition())->permissionDefinitions();
        $resetDisplay = array_values(array_filter(
            $permissions,
            static fn($permission): bool => $permission->code() === 'system.notifications.reset_display',
        ));

        self::assertCount(1, $resetDisplay);
        self::assertSame(['system.notifications.view'], $resetDisplay[0]->requires());
    }
}
