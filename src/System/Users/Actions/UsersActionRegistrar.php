<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje action transport s oddelenymi pravy mutace, lifecycle a permission preview
 */
final readonly class UsersActionRegistrar
{
    /**
     * Nastavuje registry a handlery vsech mutaci modulu uzivatelu
     */
    public function __construct(
        private ModuleActionRegistry $actions,
        private UsersEditorSaveAction $save,
        private UsersEditorCreateAction $create,
        private UsersPermissionPreviewAction $permissionsPreview,
        private UsersActivateAction $activate,
        private UsersDeactivateAction $deactivate,
        private UsersDeleteAction $delete,
        private UsersRestoreAction $restore,
        private UsersMfaResetAction $mfaReset,
    ) {}

    /**
     * Prirazuje action handlerum canonical permission a refresh metadata
     */
    public function register(): void
    {
        $moduleCode = 'system.users';
        $this->actions->register($moduleCode, new ModuleActionDefinition('save', 'system.users.edit', 'admin.common.save'), $this->save);
        $this->actions->register($moduleCode, new ModuleActionDefinition('create', 'system.users.create', 'users.actions.create'), $this->create);
        $this->actions->register($moduleCode, new ModuleActionDefinition('permissions-preview', 'system.users.manage_permissions', 'users.editor.tabs.permissions'), $this->permissionsPreview);
        $this->actions->register($moduleCode, new ModuleActionDefinition('activate', 'system.users.edit', 'users.actions.activate', new ConfirmationDefinition('users.confirm.activate'), true), $this->activate);
        $this->actions->register($moduleCode, new ModuleActionDefinition('deactivate', 'system.users.disable', 'users.actions.deactivate', new ConfirmationDefinition('users.confirm.deactivate'), true), $this->deactivate);
        $this->actions->register($moduleCode, new ModuleActionDefinition('delete', 'system.users.delete', 'users.actions.delete', new ConfirmationDefinition('users.confirm.delete'), true), $this->delete);
        $this->actions->register($moduleCode, new ModuleActionDefinition('restore', 'system.users.restore', 'users.actions.restore', new ConfirmationDefinition('users.confirm.restore'), true), $this->restore);
        $this->actions->register($moduleCode, new ModuleActionDefinition('mfa-reset', 'system.users.mfa_reset', 'users.actions.mfa_reset', new ConfirmationDefinition('users.confirm.mfa_reset'), true), $this->mfaReset);
    }
}
