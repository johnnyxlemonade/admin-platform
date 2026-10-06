<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\InternalAdminDestination;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InternalAdminDestinationTest extends TestCase
{
    #[DataProvider('safeDestinations')]
    public function testItAllowsInternalAdminDestinations(string $destination): void
    {
        self::assertTrue(InternalAdminDestination::isSafe($destination, $this->routing()));
    }

    /** @return iterable<string, array{string}> */
    public static function safeDestinations(): iterable
    {
        yield 'admin root' => ['/admin'];
        yield 'nested path' => ['/admin/users/edit/42'];
        yield 'query string' => ['/admin/users?filter=active'];
        yield 'fragment remains accepted by the existing contract' => ['/admin/users#details'];
        yield 'encoded path data remains accepted by the existing contract' => ['/admin/users/%2Fnot-a-segment'];
    }

    #[DataProvider('unsafeDestinations')]
    public function testItRejectsUnsafeOrMalformedDestinations(?string $destination): void
    {
        self::assertFalse(InternalAdminDestination::isSafe($destination, $this->routing()));
    }

    /** @return iterable<string, array{string|null}> */
    public static function unsafeDestinations(): iterable
    {
        yield 'external absolute URL' => ['https://attacker.example/admin'];
        yield 'protocol relative URL' => ['//attacker.example/admin'];
        yield 'outside Admin' => ['/account'];
        yield 'encoded Admin prefix bypass' => ['%2Fadmin%2Fusers'];
        yield 'backslash bypass' => ['/admin\\attacker'];
        yield 'newline injection' => ["/admin/users\r\nLocation: https://attacker.example"];
        yield 'non-string session value' => [null];
    }

    private function routing(): AdminRoutingConfiguration
    {
        return new AdminRoutingConfiguration('/admin');
    }
}
