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
        $this->presentations->register(new AuditEventPresentation('system.authentication.mfa_enrolled', 'auth.audit.events.mfa_enrolled', AdminIcon::ShieldCheck));
        $this->presentations->register(new AuditEventPresentation('system.authentication.mfa_disabled', 'auth.audit.events.mfa_disabled', AdminIcon::ShieldCheck));
        $this->presentations->register(new AuditEventPresentation('system.authentication.mfa_reset', 'auth.audit.events.mfa_reset', AdminIcon::ShieldCheck));
        $this->presentations->register(new AuditEventPresentation('system.authentication.mfa_challenge_failed', 'auth.audit.events.mfa_challenge_failed', AdminIcon::XCircle));
    }
}
