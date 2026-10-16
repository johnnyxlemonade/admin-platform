<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

/**
 * Materializuje modulove runtime providery v aktualnim request scope
 */
final class ModuleProviderResolver
{
    /** @var array<string, object> */
    private array $resolved = [];

    /**
     * Nastavuje root katalogy a aktualni scoped container
     */
    public function __construct(
        private readonly DataGridRegistry $dataGrids,
        private readonly EditorRegistry $editors,
        private readonly ModulePageRegistry $pages,
        private readonly ModuleActionRegistry $actions,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Rozhoduje, zda modul poskytuje datagrid
     */
    public function hasDataGrid(string $moduleCode): bool
    {
        return $this->dataGrids->has($moduleCode);
    }

    /**
     * Vraci datagrid provider aktualniho requestu
     */
    public function dataGrid(string $moduleCode): DataGridProviderInterface
    {
        return $this->service($this->dataGrids->providerServiceId($moduleCode), DataGridProviderInterface::class);
    }

    /**
     * Rozhoduje, zda modul poskytuje editor
     */
    public function hasEditor(string $moduleCode): bool
    {
        return $this->editors->has($moduleCode);
    }

    /**
     * Vraci editor provider aktualniho requestu
     */
    public function editor(string $moduleCode): EditorProviderInterface
    {
        return $this->service($this->editors->providerServiceId($moduleCode), EditorProviderInterface::class);
    }

    /**
     * Rozhoduje, zda modul poskytuje indexovou stranku
     */
    public function hasIndex(string $moduleCode): bool
    {
        return $this->pages->hasIndex($moduleCode);
    }

    /**
     * Vraci indexovy page provider aktualniho requestu
     */
    public function index(string $moduleCode): ModuleIndexPageProviderInterface
    {
        return $this->service($this->pages->indexProviderServiceId($moduleCode), ModuleIndexPageProviderInterface::class);
    }

    /**
     * Rozhoduje, zda modul poskytuje full-page editor
     */
    public function hasEditorPage(string $moduleCode): bool
    {
        return $this->pages->hasEditor($moduleCode);
    }

    /**
     * Vraci full-page editor provider aktualniho requestu
     */
    public function editorPage(string $moduleCode): ModuleEditorPageProviderInterface
    {
        return $this->service($this->pages->editorProviderServiceId($moduleCode), ModuleEditorPageProviderInterface::class);
    }

    /**
     * Rozhoduje, zda modul poskytuje modalni editor
     */
    public function hasModalEditor(string $moduleCode): bool
    {
        return $this->pages->hasModalEditor($moduleCode);
    }

    /**
     * Vraci modalni editor provider aktualniho requestu
     */
    public function modalEditor(string $moduleCode): ModuleModalEditorPageProviderInterface
    {
        return $this->service($this->pages->modalEditorProviderServiceId($moduleCode), ModuleModalEditorPageProviderInterface::class);
    }

    /**
     * Rozhoduje, zda modul poskytuje akci
     */
    public function hasAction(string $moduleCode, string $key): bool
    {
        return $this->actions->has($moduleCode, $key);
    }

    /**
     * Vraci definici a requestovy handler akce
     *
     * @return array{definition:ModuleActionDefinition,handler:ModuleActionHandlerInterface}
     */
    public function action(string $moduleCode, string $key): array
    {
        $registered = $this->actions->action($moduleCode, $key);

        return ['definition' => $registered['definition'], 'handler' => $this->service($registered['handlerServiceId'], ModuleActionHandlerInterface::class)];
    }

    /**
     * Vraci canonical requestovou instanci pozadovaneho contractu
     *
     * @template T of object
     * @param class-string<T> $expectedType
     * @return T
     */
    private function service(string $serviceId, string $expectedType): object
    {
        $service = $this->resolved[$serviceId] ??= $this->container->get($serviceId);
        if (!$service instanceof $expectedType) {
            throw new RuntimeException(sprintf('Module provider service "%s" must implement %s.', $serviceId, $expectedType));
        }

        return $service;
    }
}
