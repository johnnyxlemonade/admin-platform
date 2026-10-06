<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Http;

use Lemonade\Admin\Dashboard\Exception\DashboardWidgetApiException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Framework\Http\HttpStatus;
use PHPUnit\Framework\TestCase;

final class AdminResponseContractTest extends TestCase
{
    public function testAdminErrorEnumDefinesTheSharedErrorSemantics(): void
    {
        self::assertSame('validation_failed', AdminErrorCode::VALIDATION_FAILED->value);
        self::assertSame('permission_denied', AdminErrorCode::PERMISSION_DENIED->value);
        self::assertSame('local_actor_required', AdminErrorCode::LOCAL_ACTOR_REQUIRED->value);
        self::assertSame('not_found', AdminErrorCode::NOT_FOUND->value);
        self::assertSame('lock_conflict', AdminErrorCode::LOCK_CONFLICT->value);
        self::assertSame('unsupported_action', AdminErrorCode::UNSUPPORTED_ACTION->value);
        self::assertSame('notification_action_invalid', AdminErrorCode::NOTIFICATION_ACTION_INVALID->value);
        self::assertSame('dashboard_widget_action_invalid', AdminErrorCode::DASHBOARD_WIDGET_ACTION_INVALID->value);
        self::assertSame('unsupported_locale', AdminErrorCode::UNSUPPORTED_LOCALE->value);
    }

    public function testDashboardApiExceptionPreservesTheResponseScalarContract(): void
    {
        $exception = new DashboardWidgetApiException(
            AdminErrorCode::DASHBOARD_WIDGET_NOT_FOUND,
            HttpStatus::NOT_FOUND,
        );

        self::assertSame(HttpStatus::NOT_FOUND, $exception->statusCode());
        self::assertSame(404, $exception->status());
        self::assertSame(AdminErrorCode::DASHBOARD_WIDGET_NOT_FOUND, $exception->error());
        self::assertSame('dashboard_widget_not_found', $exception->errorCode());
    }
}
