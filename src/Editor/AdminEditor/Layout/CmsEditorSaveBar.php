<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Action\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\Action\AdminEditorSaveBarDefinition;

/**
 * Sklada spolecny save bar pro neulozene zmeny CMS editoru
 */
final class CmsEditorSaveBar
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori akce ulozeni a zahozeni zmen s upozornenim
     */
    public static function create(): AdminEditorSaveBarDefinition
    {
        return new AdminEditorSaveBarDefinition(
            primaryAction: new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.save_changes', submit: true, primary: true),
            secondaryAction: new AdminEditorActionDefinition(label: '', labelKey: 'admin.common.discard'),
            titleKey: 'admin.editor.unsaved_changes',
            descriptionKey: 'admin.editor.unsaved_changes_help',
        );
    }
}
