<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Auth\Audit\AuthenticationAuditPresentationRegistrar;
use Lemonade\Admin\System\Audit\AuditEventPresenter;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticationAuditPresentationRegistrarTest extends TestCase
{
    public function testItRegistersAuthenticationModuleAndEventPresentationKeys(): void
    {
        $registry = new AuditEventPresentationRegistry();
        (new AuthenticationAuditPresentationRegistrar($registry))->register();

        self::assertSame('auth.audit.module.name', $registry->modulePresentation('system.authentication')?->nameKey());
        self::assertSame('auth.audit.events.login', $registry->presentation('system.authentication.login')?->translationKey());
        self::assertSame('auth.audit.events.logout', $registry->presentation('system.authentication.logout')?->translationKey());
        foreach (['login_failed', 'login_throttled', 'mfa_failed', 'mfa_throttled', 'session_unlock_failed', 'session_unlock_throttled'] as $event) {
            self::assertSame('auth.audit.events.' . $event, $registry->presentation('system.authentication.' . $event)?->translationKey());
        }
    }

    public function testAuthenticationAuditTranslationKeysExistInBothLocales(): void
    {
        foreach (['cs' => ['Autentizace', 'Přihlášení', 'Odhlášení'], 'en' => ['Authentication', 'Login', 'Logout']] as $locale => $expected) {
            $catalog = require dirname(__DIR__, 3) . '/src/Auth/Resources/lang/' . $locale . '/auth.php';

            self::assertSame($expected[0], $catalog['audit']['module']['name']);
            self::assertSame($expected[1], $catalog['audit']['events']['login']);
            self::assertSame($expected[2], $catalog['audit']['events']['logout']);
            foreach (['login_failed', 'login_throttled', 'mfa_failed', 'mfa_throttled', 'session_unlock_failed', 'session_unlock_throttled'] as $event) {
                self::assertIsString($catalog['audit']['events'][$event] ?? null);
                self::assertNotSame('', $catalog['audit']['events'][$event]);
            }
        }
    }

    public function testAuditListPresenterUsesTheRegisteredAuthenticationLabels(): void
    {
        $registry = new AuditEventPresentationRegistry();
        (new AuthenticationAuditPresentationRegistrar($registry))->register();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::exactly(3))->method('get')->willReturnCallback(
            static fn(string $key): string => match ($key) {
                'auth.audit.events.login' => 'Login',
                'auth.audit.events.logout' => 'Logout',
                'auth.audit.module.name' => 'Authentication',
                default => throw new \LogicException('Unexpected translation key: ' . $key),
            },
        );
        $presenter = new AuditEventPresenter($registry, $translator);

        self::assertSame('Login', $presenter->description('system.authentication.login', []));
        self::assertSame('Logout', $presenter->description('system.authentication.logout', []));
        self::assertSame('Authentication', $presenter->moduleLabel('system.authentication'));
    }

    public function testAuditListPresenterUsesAnonymousVisitorLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('get')
            ->with('audit.actor.anonymous')
            ->willReturn('Anonymous visitor');
        $presenter = new AuditEventPresenter(new AuditEventPresentationRegistry(), $translator);

        self::assertSame('Anonymous visitor', $presenter->actor([
            'actor_type' => 'anonymous',
            'actor_email' => null,
            'actor_first_name' => null,
            'actor_last_name' => null,
            'actor_user_id' => null,
        ]));
    }

    public function testAnonymousAuditActorHasDedicatedTypeAndKey(): void
    {
        $actor = \Lemonade\Admin\Audit\AuditActor::anonymous('admin-auth');

        self::assertSame(\Lemonade\Admin\Audit\AuditActorType::Anonymous, $actor->type());
        self::assertSame('admin-auth', $actor->key());
        self::assertNull($actor->userId());
    }
}
