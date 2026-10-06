<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Feature\ModuleFeatureCategory;
use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestInterface;
use Lemonade\Admin\Modules\Feature\ModuleFeatureStateResolver;
use Lemonade\Admin\Modules\State\ModuleState;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\System\Modules\ViewModels\ModuleFeatureActionViewModel;
use Lemonade\Admin\System\Modules\ViewModels\ModuleFeaturesPageViewModel;
use Lemonade\Admin\System\Modules\ViewModels\ModuleFeatureViewModel;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sklada read-only feature projection pro management stranku modulu
 */
final class ModulesFeaturesPageProvider
{
    /**
     * Nastavuje sdilene runtime resolvery a presentation zavislosti
     */
    public function __construct(
        private readonly ModuleStateResolver $modules,
        private readonly ModuleFeatureStateResolver $features,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Promita deklarovane feature a runtime stav do management stranky bez vlastnictvi state
     */
    public function page(string $moduleCode, string $locale, bool $canManageFeatures): ?ModulePage
    {
        $module = $this->modules->state($moduleCode);
        $manifest = $this->modules->manifest($moduleCode);
        if (!$module->available() || !$manifest instanceof ModuleFeatureManifestInterface || $manifest->featureDefinitions() === []) {
            return null;
        }

        $viewFeatures = [];
        foreach ($manifest->featureDefinitions() as $definition) {
            $state = $this->features->feature($moduleCode, $definition->code());
            if ($state === null) {
                continue;
            }
            $mutable = $this->isMutable($module, $state->definition()->category(), $state->definition()->required(), $canManageFeatures);
            $readOnlyLabel = $mutable ? null : $this->readOnlyLabel($module, $state->definition()->category(), $state->definition()->required(), $canManageFeatures);
            $action = $mutable
                ? new ModuleFeatureActionViewModel(
                    $this->urls->route('admin.system.module.ajax.entity', ['module' => 'modules', 'id' => abs(crc32($moduleCode))]),
                    !$state->configuredEnabled(),
                    $this->translator->get($state->configuredEnabled() ? 'modules.features.actions.disable' : 'modules.features.actions.enable'),
                )
                : null;
            $viewFeatures[] = new ModuleFeatureViewModel(
                $state->code(),
                $this->translator->get($state->definition()->labelKey()),
                $this->translator->get('modules.features.categories.' . $state->definition()->category()->value),
                $this->translator->get($state->configuredEnabled() ? 'modules.features.states.enabled' : 'modules.features.states.disabled'),
                $this->translator->get($state->enabled() ? 'modules.features.states.enabled' : 'modules.features.states.disabled'),
                $state->definition()->required(),
                $readOnlyLabel,
                $action,
            );
        }

        $kind = $module->kind();
        $viewModel = new ModuleFeaturesPageViewModel(
            $this->translator->get($manifest->labelKey()),
            $moduleCode,
            $this->translator->get('modules.kind.' . ($kind === null ? ModuleKind::Optional->value : $kind->value)),
            $this->translator->get('modules.state.' . $this->stateKey($module)),
            $this->notice($module),
            $viewFeatures,
        );

        return new ModulePage('modules::features', $this->translator->get('modules.features.title'), [
            'page' => $viewModel,
            'locale' => $locale,
        ]);
    }

    /**
     * Urci dostupnost action v prezentaci podle runtime stavu a predane autorizace
     */
    private function isMutable(ModuleState $module, ModuleFeatureCategory $category, bool $required, bool $canManageFeatures): bool
    {
        return $canManageFeatures
            && !$module->system()
            && $module->kind() === ModuleKind::Optional
            && $module->installed()
            && $category === ModuleFeatureCategory::Toggle
            && !$required;
    }

    /**
     * Vysvetluje v presentation vrstve, proc feature nema management action
     */
    private function readOnlyLabel(ModuleState $module, ModuleFeatureCategory $category, bool $required, bool $canManageFeatures): string
    {
        if ($module->system()) {
            return $this->translator->get('modules.features.readOnly.system');
        }
        if (!$module->installed()) {
            return $this->translator->get('modules.features.readOnly.notInstalled');
        }
        if ($category !== ModuleFeatureCategory::Toggle) {
            return $this->translator->get($required ? 'modules.features.readOnly.required' : 'modules.features.readOnly.capability');
        }
        if ($required) {
            return $this->translator->get('modules.features.readOnly.required');
        }
        if (!$canManageFeatures) {
            return $this->translator->get('modules.features.readOnly.permission');
        }

        return $this->translator->get('modules.features.readOnly.capability');
    }

    /**
     * Mapuje runtime lifecycle stav na lokalizacni klic stranky
     */
    private function stateKey(ModuleState $module): string
    {
        if ($module->system()) {
            return 'system';
        }
        if (!$module->installed()) {
            return 'available';
        }

        return $module->enabled() ? 'enabled' : 'disabled';
    }

    /**
     * Pridava informacni upozorneni pro nenainstalovany nebo deaktivovany modul
     */
    private function notice(ModuleState $module): ?string
    {
        if (!$module->installed()) {
            return $this->translator->get('modules.features.notices.notInstalled');
        }
        if (!$module->enabled()) {
            return $this->translator->get('modules.features.notices.disabled');
        }

        return null;
    }
}
