<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\PermissionSection;
use Lemonade\Admin\Dashboard\DashboardWidgetContent;
use Lemonade\Admin\Dashboard\DashboardWidgetContentState;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\System\Modules\Dashboard\ModulesDashboardWidgetProvider;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje sdilene cleneni widgetu nad skutecne autorizovanou navigaci
 */
final class ModulesDashboardWidgetProviderTest extends TestCase
{
    /**
     * Sekce vychazeji z metadat a zachovavaji poradi, odkazy a ikony navigace
     */
    public function testGroupsUsePermissionSectionsAndPreserveNavigationItems(): void
    {
        $content = $this->load([
            $this->definition('cms.builder', AdminNavigationGroup::Cms, 30),
            $this->definition('cms.news', AdminNavigationGroup::Cms, 10),
            $this->definition('system.custom', AdminNavigationGroup::Content, 10, PermissionSection::ContentTypes),
            $this->definition('system.media', AdminNavigationGroup::System, 10),
        ]);

        self::assertSame(DashboardWidgetContentState::Ready, $content->state());
        self::assertSame(['groups' => [
            ['section' => 'system', 'modules' => [$this->item('system.media', 'system')]],
            ['section' => 'modules', 'modules' => [$this->item('cms.news', 'cms'), $this->item('cms.builder', 'cms')]],
            ['section' => 'content-types', 'modules' => [$this->item('system.custom', 'content')]],
        ]], $content->viewData());
    }

    /**
     * Skryte a nepristupne moduly ani prazdne sekce neunikaji do widgetu
     */
    public function testRestrictedUserSeesOnlyGrantedModules(): void
    {
        $content = $this->load([
            $this->definition('cms.news', AdminNavigationGroup::Cms, 10),
            $this->definition('cms.builder', AdminNavigationGroup::Cms, 20, superAdminOnly: true),
            $this->definition('system.media', AdminNavigationGroup::System, 10),
            $this->definition('cms.custom', AdminNavigationGroup::Content, 10, PermissionSection::ContentTypes),
            $this->definition('cms.hidden', AdminNavigationGroup::Cms, 30, visible: false),
        ], false, ['cms.news.view', 'cms.builder.view', 'cms.hidden.view']);

        self::assertSame(['groups' => [
            ['section' => 'modules', 'modules' => [$this->item('cms.news', 'cms')]],
        ]], $content->viewData());
    }

    /**
     * Bez opravneni zustava puvodni prazdny stav bez samostatnych odkazu navigace
     */
    public function testUserWithoutModulePermissionsGetsEmptyContent(): void
    {
        $content = $this->load([$this->definition('cms.news', AdminNavigationGroup::Cms, 10)], false);

        self::assertSame(DashboardWidgetContentState::Empty, $content->state());
        self::assertSame([], $content->viewData());
    }

    /**
     * Skryta registrace se nezobrazi ani superadministratorovi
     */
    public function testHiddenModulesRemainHiddenForSuperAdmin(): void
    {
        $content = $this->load([$this->definition('cms.hidden', AdminNavigationGroup::Cms, 10, visible: false)]);

        self::assertSame(DashboardWidgetContentState::Empty, $content->state());
    }

    /**
     * Sestavi provider nad navigaci se skutecnym vyhodnocenim opravneni
     *
     * @param list<AdminModuleDefinitionInterface> $definitions
     * @param list<string> $grants
     */
    private function load(array $definitions, bool $superAdmin = true, array $grants = []): DashboardWidgetContent
    {
        $groups = new AdminNavigationGroupRegistry();
        $registry = new AdminModuleRegistry($groups);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        $user = new AuthenticatedUser(1, 'editor@example.test');
        $principal = self::createStub(CurrentPrincipalProviderInterface::class);
        $principal->method('currentUser')->willReturn($user);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(static function (string $sql) use ($superAdmin, $grants): array {
            if (str_contains($sql, 'r.is_super_admin=1')) {
                return $superAdmin ? [['allowed' => 1]] : [];
            }
            if (str_contains($sql, 'JOIN system_role_permission')) {
                return array_map(static fn(string $code): array => ['code' => $code, 'name_key' => $code, 'module_code' => substr($code, 0, -5)], $grants);
            }

            return [];
        });
        $authorization = new AuthorizationService($principal, new Database($connection, self::createStub(DatabaseDriverInterface::class)));
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('get')->willReturnArgument(0);
        $navigation = new AdminNavigation($authorization, $registry, new AdminModuleRouteResolver($registry), new AdminModuleAccessPolicy($authorization, $principal), $groups, $translator);
        $provider = new ModulesDashboardWidgetProvider($navigation, $registry);
        $definition = iterator_to_array($provider->definitions())[0];

        return $provider->load(DashboardWidgetContext::forLocalUser($user, 'cs'), $definition);
    }

    /**
     * Registruje metadata bez zavislosti na znamych nazvech modulu
     */
    private function definition(
        string $code,
        AdminNavigationGroup $group,
        int $order,
        ?PermissionSection $section = null,
        bool $superAdminOnly = false,
        bool $visible = true,
    ): AdminModuleDefinitionInterface {
        $definition = self::createStub(AdminModuleDefinitionInterface::class);
        $definition->method('code')->willReturn($code);
        $definition->method('adminMetadata')->willReturn(new AdminModuleMetadata(
            nameKey: $code,
            icon: AdminIcon::Boxes,
            navigationGroup: $group,
            navigationOrder: $order,
            routeSegment: str_replace('.', '-', $code),
            navigationPermission: $code . '.view',
            superAdminOnly: $superAdminOnly,
            navigationVisible: $visible,
            permissionSection: $section,
        ));

        return $definition;
    }

    /**
     * Popisuje zachovane verejne udaje polozky navigace
     *
     * @return array{label:string,icon:string,route:string,routeParameters:array{module:string}}
     */
    private function item(string $code, string $group): array
    {
        return ['label' => $code, 'icon' => AdminIcon::Boxes->value, 'route' => 'admin.' . $group . '.module.index', 'routeParameters' => ['module' => str_replace('.', '-', $code)]];
    }
}
