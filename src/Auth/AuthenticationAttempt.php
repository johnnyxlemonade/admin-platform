<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

/**
 * Zpracovava overenou identitu uzivatele
 */
final readonly class AuthenticationAttempt
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    private function __construct(
        private ?AuthenticatedUser $user,
        private bool $inactive,
    ) {}

    /**
     * Vraci nebo zpracovava hodnotu successful pro overeni identity
     */
    public static function successful(AuthenticatedUser $user): self
    {
        return new self($user, false);
    }

    /**
     * Vraci nebo zpracovava hodnotu invalid pro overeni identity
     */
    public static function invalid(): self
    {
        return new self(null, false);
    }

    /**
     * Vraci nebo zpracovava hodnotu inactive pro overeni identity
     */
    public static function inactive(): self
    {
        return new self(null, true);
    }

    /**
     * Vraci overeneho uzivatele
     */
    public function user(): ?AuthenticatedUser
    {
        return $this->user;
    }

    /**
     * Rozhoduje stav isinactive
     */
    public function isInactive(): bool
    {
        return $this->inactive;
    }
}
