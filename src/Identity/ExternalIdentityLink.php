<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

/**
 * Nese canonical vazbu externi identity na uzivatele
 */
final readonly class ExternalIdentityLink
{
    /**
     * Vytvari vazbu s lokalnim uzivatelem
     */
    public function __construct(
        private int $userId,
        private string $providerKey,
        private string $issuer,
        private string $subject,
    ) {}

    /**
     * Vraci lokalni identifikator uzivatele
     */
    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Vraci technicky klic poskytovatele
     */
    public function providerKey(): string
    {
        return $this->providerKey;
    }

    /**
     * Vraci OIDC issuer
     */
    public function issuer(): string
    {
        return $this->issuer;
    }

    /**
     * Vraci OIDC subject
     */
    public function subject(): string
    {
        return $this->subject;
    }
}
