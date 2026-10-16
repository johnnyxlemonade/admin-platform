<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje management action transport nad canonical module runtime sluzbami
 */
final readonly class ModulesActionRegistrar
{
    /**
     * Nastavuje registry a handlery administracnich action entrypointu
     */
    public function __construct(
        private ModuleActionRegistry $actions,
    ) {}

    /**
     * Pripojuje permission a confirmation metadata k management handlerum
     */
    public function register(): void
    {
        $this->actions->register('system.modules', new ModuleActionDefinition('install', 'system.modules.install', 'modules.actions.install', new ConfirmationDefinition('modules.confirm.install'), true), ModulesInstallAction::class);
        $this->actions->register('system.modules', new ModuleActionDefinition('enable', 'system.modules.enable', 'modules.actions.enable', new ConfirmationDefinition('modules.confirm.enable'), true), ModulesEnableAction::class);
        $this->actions->register('system.modules', new ModuleActionDefinition('disable', 'system.modules.disable', 'modules.actions.disable', new ConfirmationDefinition('modules.confirm.disable'), true), ModulesDisableAction::class);
        $this->actions->register('system.modules', new ModuleActionDefinition('feature-toggle', 'system.modules.manage_features', 'modules.features.actions.toggle'), ModulesFeatureToggleAction::class);
    }
}
