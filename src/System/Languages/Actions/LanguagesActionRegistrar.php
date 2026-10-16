<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje editorove a stavove akce systemovych jazyku
 */
final readonly class LanguagesActionRegistrar
{
    public const string SAVE_HANDLER = 'admin.system.languages.action.save';
    public const string CREATE_HANDLER = 'admin.system.languages.action.create';
    public const string ENABLE_HANDLER = 'admin.system.languages.action.enable';
    public const string DISABLE_HANDLER = 'admin.system.languages.action.disable';

    public function __construct(
        private ModuleActionRegistry $actions,
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
            handlerServiceId: self::SAVE_HANDLER,
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
            handlerServiceId: self::CREATE_HANDLER,
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
            handlerServiceId: self::ENABLE_HANDLER,
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
            handlerServiceId: self::DISABLE_HANDLER,
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
            handlerServiceId: LanguagesSetDefaultAction::class,
        );
    }
}
