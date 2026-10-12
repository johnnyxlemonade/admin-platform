<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

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
 * Overuje lokalizovane popisy pro metadata systemovych Admin modulu
 */
final class AdminModuleMetadataDescriptionTest extends TestCase
{
    /**
     * Kazdy systemovy modul deklaruje popis dostupny v ceskem i anglickem katalogu
     */
    public function testSystemModuleMetadataDescriptionKeysExistInBothLocales(): void
    {
        foreach ($this->definitions() as $scope => $definition) {
            $descriptionKey = $definition->adminMetadata()->descriptionKey();

            self::assertIsString($descriptionKey);
            self::assertNotSame('', $descriptionKey);
            self::assertIsString($this->translation($scope, 'cs', $descriptionKey));
            self::assertIsString($this->translation($scope, 'en', $descriptionKey));
        }
    }

    /**
     * @return array<string, AdminModuleDefinitionInterface>
     */
    private function definitions(): array
    {
        return [
            'users' => new UsersModuleDefinition(),
            'roles' => new RolesModuleDefinition(),
            'languages' => new LanguagesModuleDefinition(),
            'media' => new MediaModuleDefinition(),
            'notifications' => new NotificationsModuleDefinition(),
            'modules' => new ModulesModuleDefinition(),
            'translations' => new TranslationsModuleDefinition(),
            'audit' => new AuditModuleDefinition(),
        ];
    }

    /**
     * Vraci preklad podle namespacovaneho klice modulu
     */
    private function translation(string $scope, string $locale, string $key): mixed
    {
        $catalog = require dirname(__DIR__, 3) . '/src/System/' . ucfirst($scope) . '/Resources/lang/' . $locale . '/' . $scope . '.php';
        $segments = explode('.', $key);
        array_shift($segments);
        $value = $catalog;

        foreach ($segments as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? null) : null;
        }

        return $value;
    }
}
