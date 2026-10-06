<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\LeagueOpenIdConnectClient;
use PHPUnit\Framework\TestCase;

final class LeagueOpenIdConnectClientTest extends TestCase
{
    public function testItDerivesTheRfc7636S256Challenge(): void
    {
        self::assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            LeagueOpenIdConnectClient::codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
        );
    }
}
