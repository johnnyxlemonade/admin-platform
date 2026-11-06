<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module\Contract;

/**
 * Materializuje sdileny provider pro konkretni databazove definovany modul
 */
interface ModuleProviderFactoryInterface
{
    /**
     * Vrati provider pozadovane capability v aktualnim request scope
     *
     * @param class-string $contract
     */
    public function createProvider(string $moduleCode, string $contract, ?string $action = null): object;
}
