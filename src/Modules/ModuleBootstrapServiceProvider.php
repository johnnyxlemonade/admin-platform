<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\CoreAuditEventServiceProvider;
use Lemonade\Admin\Authorization\CoreAuthorizationServiceProvider;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Editor\AdminEditorServiceProvider;
use Lemonade\Admin\Identity\CoreIdentityServiceProvider;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\Module\Contract\AdminModuleFileCapabilitiesInterface;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Notification\AdminNotificationServiceProvider;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\System\SystemModuleServiceProvider;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DefinitionServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Bootuje objevene moduly podle jejich manifestu a lifecycle stavu
 */
final class ModuleBootstrapServiceProvider implements DefinitionServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /** @var list<BootableServiceProviderInterface> */
    private array $bootableProviders = [];

    /**
     * Nastavuje katalog manifestu pro jeden application bootstrap
     */
    public function __construct(private readonly ModuleCatalog $catalog) {}

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
        foreach ($this->catalog->load() as $manifest) {
            $this->registerProvider($builder, $manifest);
        }
    }

    /**
     * Spousti runtime contributions bootable manifestovych provideru po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $this->registerManifestAdminContributions($container);

        foreach ($this->bootableProviders as $provider) {
            $provider->boot($container);
        }

        $this->registerModuleFileCapabilities($container);
    }

    /**
     * Zapise zakladni Admin metadata deklarovana primo objevenymi manifesty
     */
    private function registerManifestAdminContributions(ContainerInterface $container): void
    {
        foreach ($this->catalog->load() as $manifest) {
            if (!$manifest instanceof AdminModuleDefinitionInterface) {
                continue;
            }

            $container->get(AdminModuleRegistry::class)->register($manifest);
            $container->get(PermissionCatalogRegistry::class)->register(...$manifest->permissionDefinitions());
            $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($manifest);
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

        if ($provider instanceof BootableServiceProviderInterface) {
            $this->bootableProviders[] = $provider;
        }
    }

    /**
     * Registruje interni file usages rozvinute z deklaraci aktivnich Admin modulu
     */
    private function registerModuleFileCapabilities(ContainerInterface $container): void
    {
        $usages = $container->get(AdminFileUsageRegistry::class);
        foreach ($container->get(AdminModuleRegistry::class)->all() as $definition) {
            if (!$definition instanceof AdminModuleFileCapabilitiesInterface) {
                continue;
            }

            $usages->registerCapabilities($definition->code(), $definition->fileCapabilities());
        }
    }
}
