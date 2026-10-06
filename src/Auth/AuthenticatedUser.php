<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

/**
 * Zpracovava overenou identitu uzivatele
 */
final readonly class AuthenticatedUser
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(
        private int $id,
        private string $email,
        private ?string $firstName = null,
        private ?string $lastName = null,
    ) {}

    /**
     * Vraci identifikator overene identity
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Vraci e-mail overene identity
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Vraci nebo zpracovava hodnotu firstname pro overeni identity
     */
    public function firstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * Vraci nebo zpracovava hodnotu lastname pro overeni identity
     */
    public function lastName(): ?string
    {
        return $this->lastName;
    }
}
