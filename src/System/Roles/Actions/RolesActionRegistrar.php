<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Editor\EditorDispatcher;

/**
 * Registruje CRUD action transport s oddelenymi management permissions roli
 */
final class RolesActionRegistrar
{
    /**
     * Nastavuje registry a handlery action transportu roli
     */
    public function __construct(private readonly ModuleActionRegistry $actions, private readonly EditorDispatcher $editors, private readonly RolesDeleteAction $delete, private readonly RolesRestoreAction $restore) {}

    /**
     * Pripoji create, save, delete a restore akce s jejich vlastnimi permissions
     */
    public function register(): void
    {
        $this->actions->register('system.roles', new ModuleActionDefinition('save', 'system.roles.edit', 'admin.common.save'), StandardEditorAction::save($this->editors, 'system.roles'));
        $this->actions->register('system.roles', new ModuleActionDefinition('create', 'system.roles.create', 'roles.actions.create', null, true), StandardEditorAction::create($this->editors, 'system.roles'));
        $this->actions->register('system.roles', new ModuleActionDefinition('delete', 'system.roles.delete', 'roles.actions.delete', new ConfirmationDefinition('roles.confirm.delete'), true), $this->delete);
        $this->actions->register('system.roles', new ModuleActionDefinition('restore', 'system.roles.restore', 'roles.actions.restore', new ConfirmationDefinition('roles.confirm.restore'), true), $this->restore);
    }
}
