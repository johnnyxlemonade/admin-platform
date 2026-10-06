<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje management action transport nad canonical module runtime sluzbami
 */
final class ModulesActionRegistrar
{
    /**
     * Nastavuje registry a handlery administracnich action entrypointu
     */
    public function __construct(
        private readonly ModuleActionRegistry $actions,
        private readonly ModulesInstallAction $install,
        private readonly ModulesEnableAction $enable,
        private readonly ModulesDisableAction $disable,
        private readonly ModulesFeatureToggleAction $featureToggle,
    ) {}

    /**
     * Pripojuje permission a confirmation metadata k management handlerum
     */
    public function register(): void
    {
        $this->actions->register('system.modules', new ModuleActionDefinition('install', 'system.modules.install', 'modules.actions.install', new ConfirmationDefinition('modules.confirm.install'), true), $this->install);
        $this->actions->register('system.modules', new ModuleActionDefinition('enable', 'system.modules.enable', 'modules.actions.enable', new ConfirmationDefinition('modules.confirm.enable'), true), $this->enable);
        $this->actions->register('system.modules', new ModuleActionDefinition('disable', 'system.modules.disable', 'modules.actions.disable', new ConfirmationDefinition('modules.confirm.disable'), true), $this->disable);
        $this->actions->register('system.modules', new ModuleActionDefinition('feature-toggle', 'system.modules.manage_features', 'modules.features.actions.toggle'), $this->featureToggle);
    }
}
