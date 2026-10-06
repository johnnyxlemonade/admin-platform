<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Catalog;

use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestInterface;
use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestValidator;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Routing\ModulePublicRoutePrefixManifestInterface;
use Lemonade\Admin\Modules\Routing\ModulePublicRoutePrefixManifestValidator;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\ServiceProviderInterface;
use RuntimeException;

/**
 * Sestavuje katalog Composer modulu dostupnych aplikaci
 */
final class ModuleManifestDiscovery
{
    /**
     * Nastavuje cesty aplikace pro Composer discovery a diagnosticky snapshot
     */
    public function __construct(private readonly ApplicationContext $context) {}

    /**
     * Najde a overi manifesty deklarovane Composer metadata
     *
     * @return list<ModuleManifestInterface>
     */
    public function discover(): array
    {
        return self::manifestsFromClasses([
            ...ComposerModuleManifestMetadata::manifestClasses($this->context->path('vendor/composer/installed.json')),
        ]);
    }

    /**
     * Overi manifest classy z kazdeho discovery zdroje
     *
     * @param list<mixed> $classes
     * @return list<ModuleManifestInterface>
     */
    public static function manifestsFromClasses(array $classes): array
    {
        $manifests = [];
        $codes = [];
        foreach ($classes as $class) {
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
            if ($manifest instanceof ModuleFeatureManifestInterface) {
                ModuleFeatureManifestValidator::validate($manifest);
            }
            if ($manifest instanceof ModulePublicRoutePrefixManifestInterface) {
                ModulePublicRoutePrefixManifestValidator::validate($manifest);
            }

            $codes[$code] = true;
            $manifests[] = $manifest;
        }

        usort($manifests, static fn(ModuleManifestInterface $a, ModuleManifestInterface $b): int => $a->code() <=> $b->code());

        return $manifests;
    }

    /**
     * Zapise overeny katalog manifestu pro diagnostiku nebo explicitni warmup
     */
    public function writeCatalog(ModuleCatalog $catalog): void
    {
        $classes = array_map(static fn(ModuleManifestInterface $manifest): string => $manifest::class, $this->discover());
        $path = $catalog->cachePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Module catalog directory "%s" cannot be created.', $directory));
        }

        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($classes, true) . ";\n";
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('Module catalog "%s" cannot be written.', $path));
        }
    }
}
