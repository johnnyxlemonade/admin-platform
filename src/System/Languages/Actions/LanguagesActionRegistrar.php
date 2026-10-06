<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Editor\EditorDispatcher;

/**
 * Registruje editorove a stavove akce systemovych jazyku
 */
final class LanguagesActionRegistrar
{
    public function __construct(
        private readonly ModuleActionRegistry $actions,
        private readonly EditorDispatcher $editors,
        private readonly LanguagesSetEnabledAction $enable,
        private readonly LanguagesSetEnabledAction $disable,
        private readonly LanguagesSetDefaultAction $setDefault,
    ) {}

    /**
     * Prirazuje samostatna opravneni editoru, aktivace a zmeny vychoziho jazyka
     */
    public function register(): void
    {
        $this->actions->register(
            moduleCode: 'system.languages',
            definition: new ModuleActionDefinition(
                key: 'save',
                permission: 'system.languages.edit',
                labelKey: 'admin.common.save',
                refreshGrid: true,
            ),
            handler: StandardEditorAction::save(
                editors: $this->editors,
                module: 'system.languages',
            ),
        );
        $this->actions->register(
            moduleCode: 'system.languages',
            definition: new ModuleActionDefinition(
                key: 'create',
                permission: 'system.languages.create',
                labelKey: 'languages.actions.create',
                confirmation: null,
                refreshGrid: true,
            ),
            handler: StandardEditorAction::create(
                editors: $this->editors,
                module: 'system.languages',
            ),
        );
        $this->actions->register(
            moduleCode: 'system.languages',
            definition: new ModuleActionDefinition(
                key: 'enable',
                permission: 'system.languages.enable',
                labelKey: 'languages.actions.enable',
                confirmation: new ConfirmationDefinition(
                    messageKey: 'languages.confirm.enable',
                    titleKey: 'languages.confirm.enable_title',
                ),
                refreshGrid: true,
            ),
            handler: $this->enable,
        );
        $this->actions->register(
            moduleCode: 'system.languages',
            definition: new ModuleActionDefinition(
                key: 'disable',
                permission: 'system.languages.disable',
                labelKey: 'languages.actions.disable',
                confirmation: new ConfirmationDefinition(
                    messageKey: 'languages.confirm.disable',
                    titleKey: 'languages.confirm.disable_title',
                ),
                refreshGrid: true,
            ),
            handler: $this->disable,
        );
        $this->actions->register(
            moduleCode: 'system.languages',
            definition: new ModuleActionDefinition(
                key: 'set-default',
                permission: 'system.languages.set_default',
                labelKey: 'languages.actions.set_default',
                confirmation: new ConfirmationDefinition(
                    messageKey: 'languages.confirm.set_default',
                ),
                refreshGrid: true,
            ),
            handler: $this->setDefault,
        );
    }
}
