<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Validation;

/**
 * Defines the canonical password requirements for Admin users
 */
final class UserPasswordPolicy
{
    public const int MIN_LENGTH = 12;

    /**
     * Prevents instantiation of the shared policy definition
     */
    private function __construct() {}

    /**
     * Reports whether a password satisfies the canonical minimum length
     */
    public static function accepts(string $password): bool
    {
        return mb_strlen($password, 'UTF-8') >= self::MIN_LENGTH;
    }
}
