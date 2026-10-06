<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Sestavuje dostupne polozky navigace administrace
 */
final class AdminNavigation
{
    /**
     * Nastavuje hodnoty potrebne pro zobrazeni navigace
     */
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly AuthorizationService $authorization,
        private readonly AdminModuleRegistry $adminModules,
        private readonly AdminModuleAccessPolicy $access,
        private readonly AdminNavigationGroupRegistry $groups,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Vraci serazene a autorizovane polozky navigace
     * @return list<AdminNavigationEntryInterface>
     */
    public function items(): array
    {
        /** @var list<AdminNavigationItem> $standalone */
        $standalone = [
            new AdminNavigationItem(
                key: 'standalone:dashboard',
                order: 0,
                label: $this->translator->get('admin.navigation.dashboard'),
                labelKey: 'admin.navigation.dashboard',
                route: 'admin.dashboard',
                routeParameters: [],
                routePrefix: false,
                icon: AdminIcon::HouseDoor,
                moduleCode: null,
            ),
            new AdminNavigationItem(
                key: 'standalone:notifications',
                order: 5,
                label: $this->translator->get('admin.notifications.title'),
                labelKey: 'admin.notifications.title',
                route: 'admin.notifications',
                routeParameters: [],
                routePrefix: false,
                icon: AdminIcon::Bell,
                moduleCode: null,
            ),
        ];
        /** @var array<string, list<AdminNavigationItem>> $grouped */
        $grouped = [];

        foreach ($this->adminModules->all() as $definition) {
            $moduleCode = $definition->code();
            $metadata = $definition->adminMetadata();
            $permission = $metadata->navigationPermission();
            if (!$this->modules->enabled($moduleCode)
                || !$this->access->canAccess($definition)
                || ($permission !== null && !$this->authorization->hasPermission($permission))) {
                continue;
            }

            $item = new AdminNavigationItem(
                key: 'module:' . $moduleCode,
                order: $metadata->navigationOrder(),
                label: $this->translator->get($metadata->nameKey()),
                labelKey: $metadata->nameKey(),
                route: $metadata->destinationRoute(),
                routeParameters: $metadata->destinationParameters(),
                routePrefix: $metadata->destinationRoute() === 'admin.module.index',
                icon: $metadata->icon(),
                moduleCode: $moduleCode,
            );
            $groupCode = $metadata->navigationGroup();
            if ($groupCode === null) {
                $standalone[] = $item;
                continue;
            }

            $grouped[$groupCode][] = $item;
        }

        usort($standalone, static fn(AdminNavigationItem $left, AdminNavigationItem $right): int => [$left->order(), $left->key()] <=> [$right->order(), $right->key()]);

        /** @var list<AdminNavigationEntryInterface> $entries */
        $entries = [];
        foreach ($standalone as $item) {
            $entries[] = $item;
        }
        foreach ($grouped as $groupCode => $items) {
            usort($items, static fn(AdminNavigationItem $left, AdminNavigationItem $right): int => [$left->order(), $left->moduleCode()] <=> [$right->order(), $right->moduleCode()]);
            $definition = $this->groups->definition($groupCode);
            $entries[] = new AdminNavigationGroup(
                key: 'group:' . $definition->code(),
                order: $definition->order(),
                code: $definition->code(),
                label: $this->translator->get($definition->nameKey()),
                labelKey: $definition->nameKey(),
                icon: $definition->icon(),
                items: $items,
            );
        }

        usort($entries, static fn(AdminNavigationEntryInterface $left, AdminNavigationEntryInterface $right): int => [$left->order(), $left->key()] <=> [$right->order(), $right->key()]);

        return $entries;
    }
}
