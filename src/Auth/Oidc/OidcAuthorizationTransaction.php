<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

/**
 * Nese stav rozpracovaneho OIDC prihlaseni
 */
final readonly class OidcAuthorizationTransaction
{
    public function __construct(
        private string $providerKey,
        private string $state,
        private string $nonce,
        private string $codeVerifier,
        private int $issuedAt,
        private ?string $intendedPath,
        private ?int $unlockUserId = null,
        private ?string $unlockIssuer = null,
        private ?string $unlockSubject = null,
    ) {}

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function codeVerifier(): string
    {
        return $this->codeVerifier;
    }

    public function issuedAt(): int
    {
        return $this->issuedAt;
    }

    public function intendedPath(): ?string
    {
        return $this->intendedPath;
    }

    /** Vraci true pouze pro popup session unlock transakci */
    public function isSessionUnlock(): bool
    {
        return $this->unlockUserId !== null;
    }

    /** @return array{userId:int,issuer:string,subject:string}|null */
    public function unlockIdentity(): ?array
    {
        if ($this->unlockUserId === null || $this->unlockIssuer === null || $this->unlockSubject === null) {
            return null;
        }
        return ['userId' => $this->unlockUserId, 'issuer' => $this->unlockIssuer, 'subject' => $this->unlockSubject];
    }

    public function isExpired(int $now, int $ttlSeconds): bool
    {
        return $this->issuedAt + $ttlSeconds < $now;
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'providerKey' => $this->providerKey,
            'state' => $this->state,
            'nonce' => $this->nonce,
            'codeVerifier' => $this->codeVerifier,
            'issuedAt' => $this->issuedAt,
            'intendedPath' => $this->intendedPath,
            'unlockUserId' => $this->unlockUserId, 'unlockIssuer' => $this->unlockIssuer, 'unlockSubject' => $this->unlockSubject,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $providerKey = $data['providerKey'] ?? null;
        $state = $data['state'] ?? null;
        $nonce = $data['nonce'] ?? null;
        $codeVerifier = $data['codeVerifier'] ?? null;
        $issuedAt = $data['issuedAt'] ?? null;
        $intendedPath = $data['intendedPath'] ?? null;
        $unlockUserId = $data['unlockUserId'] ?? null;
        $unlockIssuer = $data['unlockIssuer'] ?? null;
        $unlockSubject = $data['unlockSubject'] ?? null;
        if (!is_string($providerKey) || !is_string($state) || !is_string($nonce) || !is_string($codeVerifier) || !is_int($issuedAt) || ($intendedPath !== null && !is_string($intendedPath)) || ($unlockUserId !== null && (!is_int($unlockUserId) || !is_string($unlockIssuer) || !is_string($unlockSubject)))) {
            return null;
        }

        return new self($providerKey, $state, $nonce, $codeVerifier, $issuedAt, $intendedPath, $unlockUserId, $unlockIssuer, $unlockSubject);
    }
}
