<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje auditni zapis a post-commit zpracovani domenovych udalosti
 */
final class CoreAuditEventServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje celou transakcni pipeline pro auditovane mutace
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(DomainEventDispatcher::class, DomainEventDispatcher::class);
        $container->singleton(AuditLogService::class, AuditLogService::class);
        $container->singleton(AuditLogWriterInterface::class, AuditLogService::class);
        $container->singleton(TransactionalEventProcessor::class, TransactionalEventProcessor::class);
    }
}
