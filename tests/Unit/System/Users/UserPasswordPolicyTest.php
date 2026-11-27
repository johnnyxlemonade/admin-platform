<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the canonical Admin user password requirements
 */
final class UserPasswordPolicyTest extends TestCase
{
    /**
     * Provides password length boundary scenarios
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function passwordLengths(): iterable
    {
        yield 'eleven characters' => ['12345678901', false];
        yield 'twelve characters' => ['123456789012', true];
        yield 'twelve multibyte characters' => ['ěščřžýáíéúůď', true];
    }

    /**
     * Verifies password length using characters instead of bytes
     */
    #[DataProvider('passwordLengths')]
    public function testItAppliesTheMinimumLength(string $password, bool $accepted): void
    {
        self::assertSame($accepted, UserPasswordPolicy::accepts($password));
    }
}
