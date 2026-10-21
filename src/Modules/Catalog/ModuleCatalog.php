<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Sklada seznam modulu dostupnych v aplikaci
 */
final class ModuleCatalog
{
    private readonly ModuleManifestDiscovery $discovery;

    /** @var list<AvailableModule>|null */
    private ?array $modules = null;

    /**
     * Nastavuje Composer discovery pro jeden application bootstrap
     */
    public function __construct(ApplicationContext $context)
    {
        $this->discovery = new ModuleManifestDiscovery($context);
    }

    /**
     * Vrati Composerem deklarovane moduly pro jeden application bootstrap
     *
     * @return list<AvailableModule>
     */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        return $this->modules = $this->discovery->discover();
    }

    /**
     * Overi Composer dostupnost modulu podle jeho stabilniho kodu
     */
    public function has(string $code): bool
    {
        foreach ($this->all() as $module) {
            if ($module->code() === $code) {
                return true;
            }
        }

        return false;
    }
}
