<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje prezentacni metadata auditnich udalosti autentizace
 */
final readonly class AuthenticationAuditPresentationRegistrar
{
    /**
     * Definuje auditni udalosti autentizace a jejich prezentaci
     *
     * @var array<string, array{translation: string, icon: AdminIcon}>
     */
    private const array EVENTS = [
        'system.authentication.login' => [
            'translation' => 'auth.audit.events.login',
            'icon' => AdminIcon::ShieldCheck,
        ],
        'system.authentication.logout' => [
            'translation' => 'auth.audit.events.logout',
            'icon' => AdminIcon::BoxArrowRight,
        ],
        'system.authentication.mfa_enrolled' => [
            'translation' => 'auth.audit.events.mfa_enrolled',
            'icon' => AdminIcon::ShieldCheck,
        ],
        'system.authentication.mfa_disabled' => [
            'translation' => 'auth.audit.events.mfa_disabled',
            'icon' => AdminIcon::ShieldCheck,
        ],
        'system.authentication.mfa_reset' => [
            'translation' => 'auth.audit.events.mfa_reset',
            'icon' => AdminIcon::ShieldCheck,
        ],
        'system.authentication.mfa_challenge_failed' => [
            'translation' => 'auth.audit.events.mfa_challenge_failed',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.login_failed' => [
            'translation' => 'auth.audit.events.login_failed',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.login_throttled' => [
            'translation' => 'auth.audit.events.login_throttled',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.mfa_failed' => [
            'translation' => 'auth.audit.events.mfa_failed',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.mfa_throttled' => [
            'translation' => 'auth.audit.events.mfa_throttled',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.session_unlock_failed' => [
            'translation' => 'auth.audit.events.session_unlock_failed',
            'icon' => AdminIcon::XCircle,
        ],
        'system.authentication.session_unlock_throttled' => [
            'translation' => 'auth.audit.events.session_unlock_throttled',
            'icon' => AdminIcon::XCircle,
        ],
    ];

    /**
     * Nastavuje registry pro modul a udalosti autentizace
     */
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Zaregistruje modul autentizace a vsechny jeho auditni udalosti
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.authentication',
            'auth.audit.module.name',
            AdminIcon::ShieldCheck,
        ));
        foreach (self::EVENTS as $code => $definition) {
            $this->presentations->register(new AuditEventPresentation(
                $code,
                $definition['translation'],
                $definition['icon'],
            ));
        }
    }
}
