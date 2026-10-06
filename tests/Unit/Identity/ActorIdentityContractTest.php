<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Identity;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Dashboard\DashboardWidgetContext;
use Lemonade\Admin\Editor\Lock\EditorLockIdentity;
use Lemonade\Admin\Notification\NotificationRecipientIdentity;
use PHPUnit\Framework\TestCase;

final class ActorIdentityContractTest extends TestCase
{
    public function testLocalUserUsesTheSameCanonicalIdentityAcrossActorFlows(): void
    {
        $user = new AuthenticatedUser(42, 'admin@example.test');
        $expectedKey = 'user:42';

        $principal = new LocalAdminPrincipal($user);
        $actorIdentity = $principal->actorIdentity();
        $lockIdentity = EditorLockIdentity::local($user);
        $dashboardContext = DashboardWidgetContext::forLocalUser($user, 'cs');
        $recipientIdentity = NotificationRecipientIdentity::local($user, ['editor']);

        self::assertSame($expectedKey, AuditActor::user($user->id())->key());
        self::assertSame($expectedKey, $actorIdentity->key());
        self::assertSame($expectedKey, $lockIdentity->key());
        self::assertSame($expectedKey, $dashboardContext->ownerKey());
        self::assertSame($expectedKey, $recipientIdentity->key());
        self::assertSame(42, $actorIdentity->localUserId());
        self::assertSame(42, $lockIdentity->localUserId());
        self::assertSame(42, $dashboardContext->localUser()->id());
        self::assertSame(42, $recipientIdentity->localUserId());
    }

    public function testLocalUserCanonicalIdentityDoesNotDependOnEmail(): void
    {
        $originalUser = new AuthenticatedUser(42, 'before@example.test');
        $renamedUser = new AuthenticatedUser(42, 'after@example.test');

        self::assertSame(
            EditorLockIdentity::local($originalUser)->key(),
            EditorLockIdentity::local($renamedUser)->key(),
        );
        self::assertSame(
            NotificationRecipientIdentity::local($originalUser, [])->key(),
            NotificationRecipientIdentity::local($renamedUser, [])->key(),
        );
    }
}
