<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Http;

use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use PHPUnit\Framework\TestCase;

final class ModuleActionConflictResponseTest extends TestCase
{
    public function testOptimisticRecordConflictKeepsTheMachineCodeWithoutLockOwnerPayload(): void
    {
        $payload = $this->responses()->moduleActionConflictPayload(new ModuleActionException(
            HttpStatus::CONFLICT,
            AdminErrorCode::LOCK_CONFLICT,
            'User record version no longer matches.',
            recordConflict: true,
        ));

        self::assertSame('lock_conflict', $payload['error']['code']);
        self::assertSame('admin.editor.record_conflict', $payload['error']['messageKey']);
        self::assertSame('admin.editor.record_conflict', $payload['messageKey']);
        self::assertArrayNotHasKey('lockedBy', $payload['error']);
        self::assertArrayNotHasKey('messageParams', $payload);
    }

    public function testEditorLockConflictKeepsTheOwnerPayload(): void
    {
        $owner = new EditorLockOwner(userId: 7, displayName: 'Ada Lovelace');
        $payload = $this->responses()->moduleActionConflictPayload(new ModuleActionException(
            HttpStatus::CONFLICT,
            AdminErrorCode::LOCK_CONFLICT,
            'Record is locked by another user.',
            lockedBy: $owner,
        ));

        self::assertSame('lock_conflict', $payload['error']['code']);
        self::assertSame('admin.editor.record_locked_by', $payload['error']['messageKey']);
        self::assertArrayHasKey('lockedBy', $payload['error']);
        self::assertSame(['userId' => 7, 'displayName' => 'Ada Lovelace'], $payload['error']['lockedBy'] ?? null);
        self::assertArrayHasKey('messageParams', $payload);
        self::assertSame(['name' => 'Ada Lovelace'], $payload['messageParams'] ?? null);
    }

    public function testLockConflictWithoutOwnerUsesTheGeneralConflictMessage(): void
    {
        $payload = $this->responses()->moduleActionConflictPayload(new ModuleActionException(
            HttpStatus::CONFLICT,
            AdminErrorCode::LOCK_CONFLICT,
            'Editor lock is not owned by the current user.',
        ));

        self::assertSame('lock_conflict', $payload['error']['code']);
        self::assertSame('admin.editor.record_conflict', $payload['error']['messageKey']);
        self::assertNull($payload['error']['lockedBy'] ?? null);
        self::assertSame('admin.editor.record_conflict', $payload['messageKey']);
        self::assertSame([], $payload['messageParams'] ?? []);
    }

    private function responses(): AdminAuthorizationResponseHandler
    {
        return new AdminAuthorizationResponseHandler(new HttpRequestInspector(), new AdminRoutingConfiguration('/admin'));
    }
}
