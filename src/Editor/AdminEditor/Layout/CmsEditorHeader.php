<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Action\AdminEditorActionDefinition;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderContextDefinition;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderDefinition;

/**
 * Sklada hlavicku CMS editoru s navigaci a volitelnym jazykovym kontextem
 */
final class CmsEditorHeader
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori hlavicku s breadcrumbs, akcemi a kontextem editoru
     */
    public static function create(string $module, string $indexUrl, ?AdminEditorHeaderContextDefinition $context, ?string $moduleName = null): AdminEditorHeaderDefinition
    {
        $titleKey = $module . '.editor.title';
        $moduleBreadcrumb = $moduleName === null
            ? ['label' => '', 'labelKey' => $module . '.module.name', 'href' => $indexUrl]
            : ['label' => $moduleName, 'href' => $indexUrl];

        return new AdminEditorHeaderDefinition(
            title: '',
            titleKey: $titleKey,
            breadcrumbs: [
                $moduleBreadcrumb,
                ['label' => '', 'labelKey' => $titleKey, 'current' => true],
            ],
            actions: [
                new AdminEditorActionDefinition('', labelKey: 'admin.common.back', href: $indexUrl),
                new AdminEditorActionDefinition('', labelKey: 'admin.common.save', submit: true, primary: true),
            ],
            context: $context,
        );
    }
}
