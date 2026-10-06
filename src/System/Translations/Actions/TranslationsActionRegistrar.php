<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Editor\EditorDispatcher;

/**
 * Registruje save override a reset na package source bez dalsi auditni vrstvy
 */
final class TranslationsActionRegistrar
{
    /**
     * Nastavuje shared action registry, editor transport a reset handler
     */
    public function __construct(
        private readonly ModuleActionRegistry $actions,
        private readonly EditorDispatcher $editors,
        private readonly TranslationsResetAction $reset,
        private readonly TranslationsBulkResetAction $bulkReset,
    ) {}

    /**
     * Pripojuje jedine editovatelne operace modulu
     */
    public function register(): void
    {
        $this->actions->register(
            'system.translations',
            new ModuleActionDefinition(
                'save',
                'system.translations.edit',
                'admin.common.save',
                null,
                true,
            ),
            StandardEditorAction::save($this->editors, 'system.translations'),
        );
        $this->actions->register(
            'system.translations',
            new ModuleActionDefinition(
                'reset',
                'system.translations.edit',
                'translations.actions.reset',
                new ConfirmationDefinition('translations.confirm.reset'),
                true,
            ),
            $this->reset,
        );
        $this->actions->register(
            'system.translations',
            new ModuleActionDefinition(
                'bulk-reset',
                'system.translations.edit',
                'translations.actions.bulk_reset',
                new ConfirmationDefinition('translations.confirm.bulk_reset'),
                true,
            ),
            $this->bulkReset,
        );
    }
}
