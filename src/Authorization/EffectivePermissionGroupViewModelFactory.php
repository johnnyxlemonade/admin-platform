<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Sklada skupiny efektivnich opravneni pro administraci
 */
final class EffectivePermissionGroupViewModelFactory
{
    /** @var array<string, string> */
    private const SHARED_MODULE_LABEL_KEYS = [
        'system.dashboard' => 'admin.dashboard.module.name',
    ];

    /**
     * Nastavi registry a preklady pro sestaveni skupin opravneni
     */
    public function __construct(
        private readonly AdminModuleRegistry $modules,
        private readonly PermissionCatalogRegistry $catalog,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Sestavi efektivni opravneni seskupena podle modulu
     *
     * @param list<array{code:string,name_key:string,module_code:string}> $permissions
     * @return list<array{moduleCode:string,label:string,labelKey:string|null,icon:string|null,permissions:list<array{code:string,label:string,labelKey:string,requires:list<string>}>}>
     */
    public function create(array $permissions): array
    {
        /** @var array<string, array{moduleCode:string,label:string,labelKey:string|null,icon:string|null,sortOrder:int,permissions:list<array{code:string,label:string,labelKey:string,requires:list<string>}>}> $groups */
        $groups = [];

        foreach ($permissions as $permission) {
            $moduleCode = $permission['module_code'];
            if (!isset($groups[$moduleCode])) {
                [$label, $labelKey, $icon, $sortOrder] = $this->modulePresentation($moduleCode);
                $groups[$moduleCode] = [
                    'moduleCode' => $moduleCode,
                    'label' => $label,
                    'labelKey' => $labelKey,
                    'icon' => $icon,
                    'sortOrder' => $sortOrder,
                    'permissions' => [],
                ];
            }

            $groups[$moduleCode]['permissions'][] = [
                'code' => $permission['code'],
                'label' => $this->translator->get($permission['name_key']),
                'labelKey' => $permission['name_key'],
                'requires' => $this->catalog->definition($permission['code'])?->requires() ?? [],
            ];
        }

        $groups = $this->sortGroups($groups);

        return array_map(static function (array $group): array {
            unset($group['sortOrder']);

            return $group;
        }, array_values($groups));
    }

    /**
     * Sestavi matici dedicnych a vlastnich opravneni role podle modulu
     *
     * @param list<array{code:string,name_key:string,module_code:string}> $catalog
     * @param list<array{code:string,name_key:string,module_code:string}> $rolePermissions
     * @param array<string,string> $overrides
     * @return list<array{moduleCode:string,label:string,labelKey:string|null,icon:string|null,permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>}>
     */
    public function createOverrideMatrix(array $catalog, array $rolePermissions, array $overrides): array
    {
        $inherited = array_fill_keys(array_column($rolePermissions, 'code'), true);
        $groups = [];
        foreach ($catalog as $permission) {
            $moduleCode = $permission['module_code'];
            if (!isset($groups[$moduleCode])) {
                [$label, $labelKey, $icon, $sortOrder] = $this->modulePresentation($moduleCode);
                $groups[$moduleCode] = ['moduleCode' => $moduleCode, 'label' => $label, 'labelKey' => $labelKey, 'icon' => $icon, 'sortOrder' => $sortOrder, 'permissions' => []];
            }
            $code = $permission['code'];
            $groups[$moduleCode]['permissions'][] = [
                'code' => $code,
                'label' => $this->translator->get($permission['name_key']),
                'labelKey' => $permission['name_key'],
                'state' => $overrides[$code] ?? (isset($inherited[$code]) ? 'inherited_allow' : 'inherited_deny'),
                'requires' => $this->catalog->definition($code)?->requires() ?? [],
            ];
        }
        $groups = $this->sortGroups($groups);

        return array_map(static function (array $group): array {
            unset($group['sortOrder']);

            return $group;
        }, array_values($groups));
    }

    /**
     * Vrati lokalizovane zobrazeni modulu pro skupinu opravneni
     *
     * @return array{string, string|null, string|null, int}
     */
    private function modulePresentation(string $moduleCode): array
    {
        if (isset(self::SHARED_MODULE_LABEL_KEYS[$moduleCode])) {
            $key = self::SHARED_MODULE_LABEL_KEYS[$moduleCode];

            return [$this->translator->get($key), $key, null, 0];
        }

        if (!$this->modules->has($moduleCode)) {
            return [$moduleCode, null, null, PHP_INT_MAX];
        }

        $metadata = $this->modules->definition($moduleCode)->adminMetadata();
        $key = $metadata->nameKey();
        $label = $this->translator->get($key);

        return [$label === $key ? $moduleCode : $label, $key, $metadata->icon()->cssClass(), $metadata->navigationOrder()];
    }

    /**
     * Seradi skupiny podle poradi modulu a jejich kodu
     *
     * @template T of array{moduleCode:string,label:string,labelKey:string|null,icon:string|null,sortOrder:int,permissions:list<array<string,mixed>>}
     * @param array<string, T> $groups
     * @return array<string, T>
     */
    private function sortGroups(array $groups): array
    {
        uasort($groups, static function (array $left, array $right): int {
            $order = $left['sortOrder'] <=> $right['sortOrder'];

            return $order !== 0 ? $order : $left['moduleCode'] <=> $right['moduleCode'];
        });

        return $groups;
    }
}
