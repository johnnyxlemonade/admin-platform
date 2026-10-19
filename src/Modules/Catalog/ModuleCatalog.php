<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Sklada seznam modulu dostupnych v aplikaci
 */
final class ModuleCatalog
{
    private readonly ModuleManifestDiscovery $discovery;

    /**
     * @var list<ModuleManifestInterface>|null
     */
    private ?array $manifests = null;

    /**
     * Nastavuje Composer discovery pro jeden application bootstrap
     */
    public function __construct(ApplicationContext $context)
    {
        $this->discovery = new ModuleManifestDiscovery($context);
    }

    /**
     * Vrati Composerem deklarovane manifesty pro jeden application bootstrap
     *
     * @return list<ModuleManifestInterface>
     */
    public function load(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        return $this->manifests = $this->discovery->discover();
    }
}
