<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\System\Audit\AuditModuleDefinition;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use Lemonade\Admin\System\Media\MediaModuleDefinition;
use Lemonade\Admin\System\Modules\ModulesModuleDefinition;
use Lemonade\Admin\System\Notifications\NotificationsModuleDefinition;
use Lemonade\Admin\System\Roles\RolesModuleDefinition;
use Lemonade\Admin\System\Translations\TranslationsModuleDefinition;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje canonical navigation skupiny systemovych Admin modulu
 */
final class AdminNavigationGroupMetadataTest extends TestCase
{
    /**
     * Systemove definice pouzivaji enumovy klic skupiny System
     */
    public function testSystemModuleDefinitionsUseTheSystemNavigationGroupEnum(): void
    {
        foreach ($this->definitions() as $definition) {
            self::assertSame(AdminNavigationGroup::System, $definition->adminMetadata()->navigationGroup);
        }
    }

    /**
     * @return list<AdminModuleDefinitionInterface>
     */
    private function definitions(): array
    {
        return [
            new UsersModuleDefinition(),
            new RolesModuleDefinition(),
            new LanguagesModuleDefinition(),
            new MediaModuleDefinition(),
            new NotificationsModuleDefinition(),
            new ModulesModuleDefinition(),
            new TranslationsModuleDefinition(),
            new AuditModuleDefinition(),
        ];
    }
}
