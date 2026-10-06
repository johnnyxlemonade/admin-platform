<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupDefinition;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\System\Languages\LanguagesModuleDefinition;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class EffectivePermissionGroupViewModelFactoryTest extends TestCase
{
    public function testItGroupsPermissionsByModuleAndUsesRegisteredModuleMetadataForTheLabel(): void
    {
        $modules = $this->usersModules();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static function (string $key, array $replacements = [], ?string $locale = null): string {
            unset($replacements, $locale);

            return match ($key) {
                'users.module.name' => 'Uživatelé',
                'users.permissions.edit' => 'Editovat uživatele',
                'users.permissions.view' => 'Zobrazit uživatele',
                default => $key,
            };
        });
        $factory = new EffectivePermissionGroupViewModelFactory($modules, $this->usersCatalog(), $translator);

        self::assertSame([
            [
                'moduleCode' => 'system.users',
                'label' => 'Uživatelé',
                'labelKey' => 'users.module.name',
                'icon' => 'bi bi-people',
                'permissions' => [
                    ['code' => 'system.users.edit', 'label' => 'Editovat uživatele', 'labelKey' => 'users.permissions.edit', 'requires' => ['system.users.view']],
                    ['code' => 'system.users.view', 'label' => 'Zobrazit uživatele', 'labelKey' => 'users.permissions.view', 'requires' => []],
                ],
            ],
        ], $factory->create([
            ['code' => 'system.users.edit', 'name_key' => 'users.permissions.edit', 'module_code' => 'system.users'],
            ['code' => 'system.users.view', 'name_key' => 'users.permissions.view', 'module_code' => 'system.users'],
        ]));
    }

    public function testItHandlesAnEmptyEffectivePermissionSet(): void
    {
        $factory = new EffectivePermissionGroupViewModelFactory($this->emptyModules(), new PermissionCatalogRegistry(), $this->createMock(TranslatorInterface::class));

        self::assertSame([], $factory->create([]));
    }

    public function testItUsesTheModuleCodeAsASafeFallbackForAnUnregisteredPermissionCatalog(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnArgument(0);
        $factory = new EffectivePermissionGroupViewModelFactory($this->emptyModules(), new PermissionCatalogRegistry(), $translator);

        self::assertSame([
            [
                'moduleCode' => 'system.legacy',
                'label' => 'system.legacy',
                'labelKey' => null,
                'icon' => null,
                'permissions' => [['code' => 'system.legacy.view', 'label' => 'legacy.permissions.view', 'labelKey' => 'legacy.permissions.view', 'requires' => []]],
            ],
        ], $factory->create([
            ['code' => 'system.legacy.view', 'name_key' => 'legacy.permissions.view', 'module_code' => 'system.legacy'],
        ]));
    }

    public function testItUsesTheSamePermissionKeyForCzechAndEnglishLabels(): void
    {
        $permissions = [['code' => 'system.users.view', 'name_key' => 'users.permissions.view', 'module_code' => 'system.users']];

        $czech = $this->createMock(TranslatorInterface::class);
        $czech->method('get')->willReturnCallback(static fn(string $key): string => $key === 'users.permissions.view' ? 'Zobrazit uživatele' : 'Uživatelé');
        $english = $this->createMock(TranslatorInterface::class);
        $english->method('get')->willReturnCallback(static fn(string $key): string => $key === 'users.permissions.view' ? 'View users' : 'Users');

        $modules = $this->usersModules();

        self::assertSame('Zobrazit uživatele', (new EffectivePermissionGroupViewModelFactory($modules, $this->usersCatalog(), $czech))->create($permissions)[0]['permissions'][0]['label']);
        self::assertSame('View users', (new EffectivePermissionGroupViewModelFactory($modules, $this->usersCatalog(), $english))->create($permissions)[0]['permissions'][0]['label']);
    }

    public function testItUsesRegisteredModuleOrderAndIconsForPermissionGroups(): void
    {
        $modules = $this->emptyModules();
        $modules->register(new UsersModuleDefinition());
        $modules->register(new LanguagesModuleDefinition());
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnArgument(0);

        $groups = (new EffectivePermissionGroupViewModelFactory($modules, $this->usersAndLanguagesCatalog(), $translator))->create([
            ['code' => 'system.users.view', 'name_key' => 'users.permissions.view', 'module_code' => 'system.users'],
            ['code' => 'system.languages.view', 'name_key' => 'languages.permissions.view', 'module_code' => 'system.languages'],
        ]);

        self::assertSame(['system.users', 'system.languages'], array_column($groups, 'moduleCode'));
        self::assertSame('bi bi-people', $groups[0]['icon']);
        self::assertSame('bi bi-sliders', $groups[1]['icon']);
    }

    private function usersModules(): AdminModuleRegistry
    {
        $modules = $this->emptyModules();
        $modules->register(new UsersModuleDefinition());

        return $modules;
    }

    private function usersCatalog(): PermissionCatalogRegistry
    {
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(...(new UsersModuleDefinition())->permissionDefinitions());

        return $catalog;
    }

    private function usersAndLanguagesCatalog(): PermissionCatalogRegistry
    {
        $catalog = $this->usersCatalog();
        $catalog->register(...(new LanguagesModuleDefinition())->permissionDefinitions());

        return $catalog;
    }

    private function emptyModules(): AdminModuleRegistry
    {
        $groups = new AdminNavigationGroupRegistry();
        $groups->register(new AdminNavigationGroupDefinition('system', 'admin.navigation.system', 50, AdminIcon::Gear));

        return new AdminModuleRegistry($groups);
    }
}
