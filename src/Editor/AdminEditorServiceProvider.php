<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\Http\Controller\EditorModalController;
use Lemonade\Admin\Editor\Lock\EditorLockController;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaser;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaserInterface;
use Lemonade\Admin\Editor\Lock\EditorLockRouteRegistrar;
use Lemonade\Admin\Editor\Routing\EditorModalRouteRegistrar;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Presentation\AdminFileUploadComponent;
use Lemonade\Admin\Presentation\AdminPresentationServiceProvider;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\View\ViewHelpers;
use Lemonade\Framework\View\ViewRendererInterface;

/**
 * Registruje sdileny editor protocol, zamky a jejich HTTP endpointy
 */
final readonly class AdminEditorServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro editory modulu
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [
            AdminServiceProvider::class,
            AdminModuleTransportServiceProvider::class,
            AdminPresentationServiceProvider::class,
        ];
    }

    /**
     * Zapisuje editor registry, dispatching, zamky a route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(EditorRegistry::class, EditorRegistry::class);
        $container->scoped(EditorDispatcher::class, EditorDispatcher::class);
        $container->scoped(AdminEditorFieldRenderer::class, static fn(ContainerInterface $container): AdminEditorFieldRenderer => new AdminEditorFieldRenderer(
            helpers: $container->get(ViewHelpers::class),
            files: $container->get(AdminFileUploadComponent::class),
        ));
        $container->decorate(
            ViewRendererInterface::class,
            static function (ContainerInterface $container, mixed $inner): AdminEditorViewRenderer {
                if (!$inner instanceof ViewRendererInterface) {
                    throw new \RuntimeException(sprintf('Decorated view service must implement %s.', ViewRendererInterface::class));
                }

                return new AdminEditorViewRenderer(
                    inner: $inner,
                    fields: $container->get(AdminEditorFieldRenderer::class),
                );
            },
        );
        $container->transient(EditorLockOwnerReleaserInterface::class, EditorLockOwnerReleaser::class);
        $container->scoped(EditorLockController::class, EditorLockController::class);
        $container->scoped(EditorModalController::class, EditorModalController::class);
    }

    /**
     * Registruje editor routy po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(EditorLockRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(EditorModalRouteRegistrar::class));
    }
}
