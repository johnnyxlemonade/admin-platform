<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action;

use InvalidArgumentException;
use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;

/**
 * Uchovava akce dostupne pro jednotlive moduly
 */
final class ModuleActionRegistry
{
    /** @var array<string, array<string, array{definition:ModuleActionDefinition,handler:ModuleActionHandlerInterface}>> */
    private array $actions = [];

    /**
     * Zaregistruje akci modulu pod unikatnim URL bezpecnym klicem
     */
    public function register(string $moduleCode, ModuleActionDefinition $definition, ModuleActionHandlerInterface $handler): void
    {
        $key = $definition->key();
        if (preg_match('/^[a-z][a-z0-9-]*$/', $key) !== 1 || isset($this->actions[$moduleCode][$key])) {
            throw new InvalidArgumentException('Module action key must be unique and URL-safe.');
        }
        $this->actions[$moduleCode][$key] = ['definition' => $definition, 'handler' => $handler];
    }

    /**
     * Zjisti zda modul poskytuje akci se zadanym klicem
     */
    public function has(string $moduleCode, string $key): bool
    {
        return isset($this->actions[$moduleCode][$key]);
    }

    /**
     * Vrati registrovanou definici a obsluhu akce
     *
     * @return array{definition:ModuleActionDefinition,handler:ModuleActionHandlerInterface}
     */
    public function action(string $moduleCode, string $key): array
    {
        return $this->actions[$moduleCode][$key];
    }
}
