<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use RuntimeException;

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
    public function __construct(private readonly ApplicationContext $context)
    {
        $this->discovery = new ModuleManifestDiscovery($context);
    }

    public function cachePath(): string
    {
        return $this->context->resolveCachePath('modules.php');
    }

    /**
     * Vrati Composerem deklarovane manifesty bez zavislosti na generovanem souboru
     *
     * @return list<ModuleManifestInterface>
     */
    public function load(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        if (is_file($this->context->path('vendor/composer/installed.json'))) {
            return $this->manifests = $this->discovery->discover();
        }

        $path = $this->cachePath();
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Composer installed metadata and diagnostic module catalog are missing (%s).', $path));
        }
        $classes = require $path;
        if (!is_array($classes) || !array_is_list($classes)) {
            throw new RuntimeException('Diagnostic module catalog is invalid.');
        }

        return $this->manifests = ModuleManifestDiscovery::manifestsFromClasses($classes);
    }
}
