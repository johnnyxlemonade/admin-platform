<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Notification\Contract\NotificationInboxRepositoryInterface;
use Lemonade\Admin\Notification\Http\Controller\NotificationController;
use Lemonade\Admin\Notification\Http\Controller\NotificationsController;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\Routing\NotificationRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje osobni notifikace administrace a jejich HTTP rozhrani
 */
final class AdminNotificationServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro osobni notifikace
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje notifikacni sluzby, controllery a route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(NotificationModel::class, NotificationModel::class);
        $container->singleton(
            NotificationInboxRepositoryInterface::class,
            static fn(ContainerInterface $container): NotificationModel => $container->get(NotificationModel::class),
        );
        $container->singleton(NotificationRecipientIdentityResolver::class, NotificationRecipientIdentityResolver::class);
        $container->singleton(NotificationPresentation::class, NotificationPresentation::class);
        $container->singleton(NotificationPublicationService::class, NotificationPublicationService::class);
        $container->singleton(NotificationLifecycleService::class, NotificationLifecycleService::class);
        $container->singleton(NotificationService::class, NotificationService::class);
        $container->scoped(NotificationController::class, NotificationController::class);
        $container->scoped(NotificationsController::class, NotificationsController::class);
        $container->singleton(NotificationRouteRegistrar::class, NotificationRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(NotificationRouteRegistrar::class));
    }
}
