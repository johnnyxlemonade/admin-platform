<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Notification\Contract\NotificationInboxRepositoryInterface;
use Lemonade\Admin\Notification\Http\Controller\NotificationController;
use Lemonade\Admin\Notification\Http\Controller\NotificationsController;
use Lemonade\Admin\Notification\Migrations\CreateNotifications;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\Routing\NotificationRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;

/**
 * Registruje osobni notifikace administrace a jejich HTTP rozhrani
 */
final readonly class AdminNotificationServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
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
        $container->transient(
            NotificationInboxRepositoryInterface::class,
            NotificationModel::class,
        );
        $container->scoped(NotificationController::class, NotificationController::class);
        $container->scoped(NotificationsController::class, NotificationsController::class);
    }

    /**
     * Registruje notification routu a schema migraci po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(NotificationRouteRegistrar::class));
        $container->get(MigrationRegistry::class)->register(CreateNotifications::class);
    }
}
