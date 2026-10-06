<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje auditni podobu prihlaseni
 */
final class AuthenticationAuditPresentationRegistrar
{
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.authentication',
            'auth',
            'auth.audit.module.name',
            AdminIcon::ShieldCheck,
        ));
        $this->presentations->register(new AuditEventPresentation(
            'system.authentication.login',
            'auth.audit.events.login',
            AdminIcon::ShieldCheck,
        ));
        $this->presentations->register(new AuditEventPresentation(
            'system.authentication.logout',
            'auth.audit.events.logout',
            AdminIcon::BoxArrowRight,
        ));
    }
}
