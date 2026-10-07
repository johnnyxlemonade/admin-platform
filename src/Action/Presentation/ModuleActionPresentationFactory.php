<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Presentation;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionRisk;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Sestavuje autorizovane akce pro radky datove tabulky
 */
class ModuleActionPresentationFactory
{
    /**
     * Nastavi sluzby pro sestaveni akce radku
     */
    public function __construct(
        private readonly ModuleActionRegistry $actions,
        private readonly AdminModuleRouteResolver $routes,
        private readonly AuthorizationService $authorization,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Vrati akci radku pokud ma uzivatel potrebne opravneni
     */
    public function rowAction(
        string $moduleCode,
        string $action,
        int $entityId,
        DataGridRowActionPlacement $placement = DataGridRowActionPlacement::Secondary,
        DataGridRowActionRisk $risk = DataGridRowActionRisk::Normal,
    ): ?DataGridRowActionDefinition {
        $definition = $this->actions->action($moduleCode, $action)['definition'];
        if (!$this->authorization->hasPermission($definition->permission())) {
            return null;
        }

        $routeSegment = $this->routes->segmentForModuleCode($moduleCode);

        $actionPresentation = new DataGridRowActionDefinition(
            key: $definition->key(),
            label: $this->translator->get($definition->labelKey()),
            url: $this->urls->route(
                name: $this->routes->managementRouteNameForModuleCode($moduleCode, 'ajax.entity'),
                params: ['module' => $routeSegment, 'id' => $entityId],
            ),
            method: 'POST',
            confirmation: $definition->confirmation(),
            kind: DataGridRowActionKind::Mutation,
            placement: $placement,
            risk: $risk,
            refresh: $definition->refreshGrid(),
        );
        $icon = DataGridRowActionIcon::forKey($definition->key());

        return $icon === null ? $actionPresentation : $actionPresentation->withIcon($icon);
    }
}
