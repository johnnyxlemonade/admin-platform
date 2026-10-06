<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestDefinition;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Drzi udaje manifestu volitelne funkce
 */
class ModuleFeatureManifestDefinition extends ModuleManifestDefinition implements ModuleFeatureManifestInterface
{
    /**
     * @param class-string<ServiceProviderInterface> $runtimeProvider
     * @param list<ModuleFeatureDefinition> $featureDefinitions
     */
    public function __construct(
        string $code,
        ModuleKind $kind,
        string $runtimeProvider,
        string $labelKey = '',
        private readonly array $featureDefinitions = [],
    ) {
        parent::__construct($code, $kind, $runtimeProvider, $labelKey);
    }

    /** @return list<ModuleFeatureDefinition> */
    public function featureDefinitions(): array
    {
        return $this->featureDefinitions;
    }
}
