<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Audit;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use Lemonade\Admin\System\Notifications\NotificationsModuleDefinition;
use Lemonade\Admin\System\Roles\RolesModuleDefinition;
use Lemonade\Admin\System\Translations\TranslationsModuleDefinition;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje zachovani presentation udalosti vlastnenych Admin moduly
 */
final class ModuleOwnedAuditPresentationTest extends TestCase
{
    public function testItRegistersAllMigratedModuleOwnedEventPresentations(): void
    {
        foreach ([
            [new UsersModuleDefinition(), 'updated', 'users.audit.events.updated', AdminIcon::PencilSquare],
            [new RolesModuleDefinition(), 'permissions_changed', 'roles.audit.events.permissions_changed', AdminIcon::ShieldLock],
            [new LanguagesModuleDefinition(), 'default_changed', 'languages.audit.events.default_changed', AdminIcon::Check2],
            [new NotificationsModuleDefinition(), 'display_reset', 'notifications.audit.events.display_reset', AdminIcon::ArrowCounterclockwise],
            [new TranslationsModuleDefinition(), 'override_removed', 'translations.audit.events.removed', AdminIcon::ArrowCounterclockwise],
        ] as [$definition, $suffix, $translationKey, $icon]) {
            self::assertInstanceOf(AdminModuleDefinitionInterface::class, $definition);
            $registry = new AuditEventPresentationRegistry();
            $registry->registerModuleDefinition($definition);

            $presentation = $registry->presentation($definition->code() . '.' . $suffix);

            self::assertNotNull($presentation);
            self::assertSame($translationKey, $presentation->translationKey());
            self::assertSame($icon, $presentation->icon());
        }
    }
}
