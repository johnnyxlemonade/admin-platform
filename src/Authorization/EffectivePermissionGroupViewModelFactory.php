<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Sklada skupiny efektivnich opravneni pro administraci
 */
final readonly class EffectivePermissionGroupViewModelFactory
{
    /** @var array<string, string> */
    private const array SHARED_MODULE_LABEL_KEYS = [
        'system.dashboard' => 'admin.dashboard.module.name',
    ];

    /**
     * Nastavi registry a preklady pro sestaveni skupin opravneni
     */
    public function __construct(
        private AdminModuleRegistry $modules,
        private PermissionCatalogRegistry $catalog,
        private TranslatorInterface $translator,
    ) {}

    /**
     * Sestavi efektivni opravneni seskupena podle modulu
     *
     * @param list<array{code:string,name_key:string,module_code:string}> $permissions
     * @return list<array{moduleCode:string,section:string,label:string,labelKey:string|null,description:string|null,descriptionKey:string|null,icon:string|null,permissions:list<array{code:string,label:string,labelKey:string,requires:list<string>}>}>
     */
    public function create(array $permissions): array
    {
        /** @var array<string, array{moduleCode:string,section:string,label:string,labelKey:string|null,description:string|null,descriptionKey:string|null,icon:string|null,sortOrder:int,permissions:list<array{code:string,label:string,labelKey:string,requires:list<string>}>}> $groups */
        $groups = [];

        foreach ($permissions as $permission) {
            $definition = $this->catalog->definition($permission['code']);
            if ($definition === null) {
                continue;
            }

            $moduleCode = $permission['module_code'];
            if (!isset($groups[$moduleCode])) {
                [$label, $labelKey, $description, $descriptionKey, $icon, $sortOrder] = $this->modulePresentation($moduleCode);
                $groups[$moduleCode] = [
                    'moduleCode' => $moduleCode,
                    'section' => $this->section($moduleCode)->value,
                    'label' => $label,
                    'labelKey' => $labelKey,
                    'description' => $description,
                    'descriptionKey' => $descriptionKey,
                    'icon' => $icon,
                    'sortOrder' => $sortOrder,
                    'permissions' => [],
                ];
            }

            $groups[$moduleCode]['permissions'][] = [
                'code' => $permission['code'],
                'label' => $this->translator->get($permission['name_key']),
                'labelKey' => $permission['name_key'],
                'requires' => $definition->requires(),
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
     * @return list<array{moduleCode:string,section:string,label:string,labelKey:string|null,description:string|null,descriptionKey:string|null,icon:string|null,permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>}>
     */
    public function createOverrideMatrix(array $catalog, array $rolePermissions, array $overrides): array
    {
        $inherited = array_fill_keys(array_column($rolePermissions, 'code'), true);
        $groups = [];
        foreach ($catalog as $permission) {
            $definition = $this->catalog->definition($permission['code']);
            if ($definition === null) {
                continue;
            }

            $moduleCode = $permission['module_code'];
            if (!isset($groups[$moduleCode])) {
                [$label, $labelKey, $description, $descriptionKey, $icon, $sortOrder] = $this->modulePresentation($moduleCode);
                $groups[$moduleCode] = [
                    'moduleCode' => $moduleCode,
                    'section' => $this->section($moduleCode)->value,
                    'label' => $label,
                    'labelKey' => $labelKey,
                    'description' => $description,
                    'descriptionKey' => $descriptionKey,
                    'icon' => $icon,
                    'sortOrder' => $sortOrder,
                    'permissions' => [],
                ];
            }
            $code = $permission['code'];
            $groups[$moduleCode]['permissions'][] = [
                'code' => $code,
                'label' => $this->translator->get($permission['name_key']),
                'labelKey' => $permission['name_key'],
                'state' => $overrides[$code] ?? (isset($inherited[$code]) ? 'inherited_allow' : 'inherited_deny'),
                'requires' => $definition->requires(),
            ];
        }
        $groups = $this->sortGroups($groups);

        return array_map(static function (array $group): array {
            unset($group['sortOrder']);

            return $group;
        }, array_values($groups));
    }

    /**
     * Respektuje explicitni sekci modulu a zachovava dosavadni systemove cleneni
     */
    private function section(string $moduleCode): PermissionSection
    {
        if ($this->modules->has($moduleCode)) {
            $section = $this->modules->definition($moduleCode)->adminMetadata()->permissionSection();
            if ($section !== null) {
                return $section;
            }
        }

        return str_starts_with($moduleCode, 'system.') ? PermissionSection::System : PermissionSection::Modules;
    }

    /**
     * Vrati lokalizovane zobrazeni modulu pro skupinu opravneni
     *
     * @return array{string, string|null, string|null, string|null, string|null, int}
     */
    private function modulePresentation(string $moduleCode): array
    {
        if (isset(self::SHARED_MODULE_LABEL_KEYS[$moduleCode])) {
            $key = self::SHARED_MODULE_LABEL_KEYS[$moduleCode];

            return [$this->translator->get($key), $key, null, null, null, 0];
        }

        if (!$this->modules->has($moduleCode)) {
            return [$moduleCode, null, null, null, null, PHP_INT_MAX];
        }

        $metadata = $this->modules->definition($moduleCode)->adminMetadata();
        $key = $metadata->name() === null ? $metadata->nameKey() : null;
        $label = $metadata->name() ?? $this->translator->get($metadata->nameKey());
        $descriptionKey = $metadata->description() === null ? $metadata->descriptionKey() : null;
        $description = $metadata->description() ?? ($descriptionKey === null ? null : $this->translator->get($descriptionKey));

        if ($description === $descriptionKey) {
            $description = null;
            $descriptionKey = null;
        }

        return [$label === $key ? $moduleCode : $label, $key, $description, $descriptionKey, $metadata->icon()->cssClass(), $metadata->navigationOrder()];
    }

    /**
     * Seradi skupiny podle poradi modulu a jejich kodu
     *
     * @template T of array{moduleCode:string,section:string,label:string,labelKey:string|null,description:string|null,descriptionKey:string|null,icon:string|null,sortOrder:int,permissions:list<array<string,mixed>>}
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
