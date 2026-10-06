<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\OidcAuthorizationCallback;
use PHPUnit\Framework\TestCase;

final class OidcAuthorizationCallbackTest extends TestCase
{
    public function testItRepresentsAnAuthorizationError(): void
    {
        $callback = OidcAuthorizationCallback::fromQuery([
            'error' => 'access_denied',
            'state' => 'opaque-state',
        ]);

        self::assertTrue($callback->hasError());
        self::assertSame('access_denied', $callback->error());
        self::assertSame('opaque-state', $callback->state());
        self::assertNull($callback->code());
    }
}
