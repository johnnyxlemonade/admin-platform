<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use RuntimeException;

/**
 * Sestavuje katalog Composer modulu dostupnych aplikaci
 */
final readonly class ModuleManifestDiscovery
{
    /**
     * Nastavuje cesty aplikace pro Composer discovery
     */
    public function __construct(private readonly ApplicationContext $context) {}

    /**
     * Najde a overi moduly deklarovane Composer metadata
     *
     * @return list<AvailableModule>
     */
    public function discover(): array
    {
        $modules = [];
        $codes = [];
        foreach (ComposerModuleManifestMetadata::modules($this->context->path('vendor/composer/installed.json')) as $metadata) {
            $class = $metadata['manifestClass'];
            if (!is_string($class) || !class_exists($class)) {
                throw new RuntimeException('Module manifest class must be autoloadable.');
            }
            if (!is_subclass_of($class, ModuleManifestInterface::class)) {
                throw new RuntimeException(sprintf('Module manifest "%s" must implement %s.', $class, ModuleManifestInterface::class));
            }

            $manifest = new $class();
            $code = $manifest->code();
            if ($code === '' || isset($codes[$code])) {
                throw new RuntimeException(sprintf('Module code "%s" must be unique and non-empty.', $code));
            }
            $provider = $manifest->runtimeProvider();
            if (!class_exists($provider) || !is_subclass_of($provider, ServiceProviderInterface::class)) {
                throw new RuntimeException(sprintf('Runtime provider "%s" for module "%s" must implement %s.', $provider, $code, ServiceProviderInterface::class));
            }
            $codes[$code] = true;
            $modules[] = new AvailableModule(
                manifest: $manifest,
                packageName: $metadata['packageName'],
                version: $metadata['version'],
                description: $metadata['description'],
            );
        }

        usort($modules, static fn(AvailableModule $left, AvailableModule $right): int => $left->code() <=> $right->code());

        return $modules;
    }
}
