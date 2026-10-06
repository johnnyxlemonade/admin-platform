<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Controller;

use InvalidArgumentException;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\DataGrid\Query\DataGridQueryValidator;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Modules\State\ModuleManager;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro datagrid
 */
final class DataGridController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminModuleRouteResolver $routes,
        private readonly AdminModuleAccessPolicy $moduleAccess,
        private readonly ModuleManager $moduleManager,
        private readonly DataGridRegistry $grids,
        private readonly DataGridQueryValidator $queries,
        private readonly AuthorizationService $authorization,
        private readonly AdminAuthorizationResponseHandler $authorizationResponses,
        private readonly AdminUiLocale $locale,
        private readonly Responses $responses,
    ) {}

    /**
     * Vykresluje nebo vraci data pozadovane administracni stranky
     */
    public function index(string $module, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $definition = $this->routes->resolve($module);
        } catch (\RuntimeException) {
            return $this->notFound(AdminErrorCode::MODULE_NOT_FOUND, 'Module not found.');
        }
        $moduleCode = $definition->code();

        if (!$this->moduleManager->enabled($moduleCode)) {
            return $this->notFound(AdminErrorCode::MODULE_NOT_AVAILABLE, 'Module is not available.');
        }

        if (!$this->moduleAccess->canAccess($definition)) {
            return $this->responses->json($this->authorizationResponses->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value);
        }

        if (!$this->grids->has($moduleCode)) {
            return $this->notFound(AdminErrorCode::DATAGRID_NOT_SUPPORTED, 'Module does not provide a DataGrid.');
        }

        $grid = $this->grids->provider($moduleCode);
        if (!$this->authorization->hasPermission($grid->permission())) {
            return $this->responses->json($this->authorizationResponses->forbiddenPayload(), HttpStatusCode::FORBIDDEN->value);
        }
        $requestData = new RequestData($request);
        $this->locale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));
        $dataGridDefinition = $grid->dataGridDefinition();
        try {
            $query = $this->queries->validate($dataGridDefinition, $requestData->queryAll());
        } catch (InvalidArgumentException $error) {
            return $this->responses->json(['error' => ['code' => AdminErrorCode::VALIDATION_FAILED->value, 'message' => $error->getMessage()]], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }
        $result = $grid->execute($query);

        return $this->responses->json([
            'columns' => array_map(static fn($column): array => [
                'key' => $column->key(), 'translationKey' => $column->translationKey(), 'sortable' => $column->sortKey() !== null, 'sortKey' => $column->sortKey(), 'class' => $column->class(),
            ], $dataGridDefinition->columns()),
            'filters' => array_map(static fn($filter): array => [
                'key' => $filter->key(), 'options' => $filter->options(), 'value' => $query->filter($filter->key()),
            ], $dataGridDefinition->filters()),
            'items' => array_map(static fn($item): array => $item->toArray(), $result->items()),
            'sort' => ['column' => $query->sortKey(), 'direction' => $query->sortDirection()],
            'pagination' => [
                'page' => $result->page(), 'perPage' => $result->perPage(), 'total' => $result->total(), 'pages' => $result->total() === 0 ? 0 : (int) ceil($result->total() / $result->perPage()),
            ],
        ]);
    }

    /**
     * Vraci odpoved pro nenalezeny administracni cil
     */
    private function notFound(AdminErrorCode $code, string $message): ResponseInterface
    {
        return $this->responses->json(['error' => ['code' => $code->value, 'message' => $message]], HttpStatusCode::NOT_FOUND->value);
    }
}
