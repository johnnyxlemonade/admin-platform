<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje CRUD action transport s oddelenymi management permissions roli
 */
final readonly class RolesActionRegistrar
{
    public const string SAVE_HANDLER = 'admin.system.roles.action.save';
    public const string CREATE_HANDLER = 'admin.system.roles.action.create';

    /**
     * Nastavuje registry a handlery action transportu roli
     */
    public function __construct(private ModuleActionRegistry $actions) {}

    /**
     * Pripoji create, save, delete a restore akce s jejich vlastnimi permissions
     */
    public function register(): void
    {
        $this->actions->register('system.roles', new ModuleActionDefinition('save', 'system.roles.edit', 'admin.common.save'), self::SAVE_HANDLER);
        $this->actions->register('system.roles', new ModuleActionDefinition('create', 'system.roles.create', 'roles.actions.create', null, true), self::CREATE_HANDLER);
        $this->actions->register('system.roles', new ModuleActionDefinition('delete', 'system.roles.delete', 'roles.actions.delete', new ConfirmationDefinition('roles.confirm.delete'), true), RolesDeleteAction::class);
        $this->actions->register('system.roles', new ModuleActionDefinition('restore', 'system.roles.restore', 'roles.actions.restore', new ConfirmationDefinition('roles.confirm.restore'), true), RolesRestoreAction::class);
    }
}
