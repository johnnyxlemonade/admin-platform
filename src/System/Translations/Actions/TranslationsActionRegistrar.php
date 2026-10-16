<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje save override a reset na package source bez dalsi auditni vrstvy
 */
final readonly class TranslationsActionRegistrar
{
    public const string SAVE_HANDLER = 'admin.system.translations.action.save';

    /**
     * Nastavuje shared action registry, editor transport a reset handler
     */
    public function __construct(
        private ModuleActionRegistry $actions,
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
            self::SAVE_HANDLER,
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
            TranslationsResetAction::class,
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
            TranslationsBulkResetAction::class,
        );
    }
}
