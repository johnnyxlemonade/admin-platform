<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use InvalidArgumentException;

/**
 * Zpracovava overenou identitu uzivatele
 */
final readonly class VerifiedExternalIdentity
{
    /**
     * Nastavuje data potrebna pro overeni identity
     * @param list<string> $groups
     */
    public function __construct(
        private string $provider,
        private string $issuer,
        private string $subject,
        private ?string $email,
        private array $groups = [],
        private ?string $firstName = null,
        private ?string $lastName = null,
    ) {
        if (trim($provider) === '' || trim($issuer) === '' || trim($subject) === '') {
            throw new InvalidArgumentException('A verified external identity requires provider, issuer, and subject.');
        }
    }

    /**
     * Vraci zdroj overene identity
     */
    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * Rozhoduje stav issuer
     */
    public function issuer(): string
    {
        return $this->issuer;
    }

    /**
     * Vraci nebo zpracovava hodnotu subject pro overeni identity
     */
    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * Vraci e-mail overene identity
     */
    public function email(): ?string
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

    /**
     * Vraci nebo zpracovava hodnotu groups pro overeni identity
     * @return list<string>
     */
    public function groups(): array
    {
        return $this->groups;
    }
}
