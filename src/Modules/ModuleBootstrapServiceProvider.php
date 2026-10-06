<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Audit\CoreAuditEventServiceProvider;
use Lemonade\Admin\Authorization\CoreAuthorizationServiceProvider;
use Lemonade\Admin\Editor\AdminEditorServiceProvider;
use Lemonade\Admin\Identity\CoreIdentityServiceProvider;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Notification\AdminNotificationServiceProvider;
use Lemonade\Admin\System\SystemModuleServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Bootuje objevene moduly podle jejich manifestu a lifecycle stavu
 */
final class ModuleBootstrapServiceProvider implements DefinitionServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Uvadi Admin providery, ktere pripravuji registry pred bootstrapem modulu
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [
            CoreIdentityServiceProvider::class,
            CoreAuditEventServiceProvider::class,
            CoreModuleServiceProvider::class,
            CoreAuthorizationServiceProvider::class,
            AdminServiceProvider::class,
            AdminModuleTransportServiceProvider::class,
            AdminEditorServiceProvider::class,
            AdminNotificationServiceProvider::class,
            SystemModuleServiceProvider::class,
        ];
    }

    /**
     * Nacte katalog a zaregistruje providery aktivnich manifestu
     */
    public function register(ContainerBuilderInterface $builder): void
    {
        foreach ($builder->get(ModuleCatalog::class)->load() as $manifest) {
            $this->registerProvider($builder, $manifest);
        }
    }

    /**
     * Overi runtime provider manifestu a zapise jeho definice do kontejneru
     */
    private function registerProvider(ContainerBuilderInterface $builder, ModuleManifestInterface $manifest): void
    {
        $providerClass = $manifest->runtimeProvider();
        $provider = new $providerClass();

        if (!$provider instanceof ServiceProviderInterface) {
            throw new \LogicException(sprintf(
                'Module provider "%s" for module "%s" must implement %s.',
                $providerClass,
                $manifest->code(),
                ServiceProviderInterface::class,
            ));
        }

        $provider->register($builder);
    }
}
