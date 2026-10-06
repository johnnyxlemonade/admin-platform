<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

/**
 * Nese stav rozpracovaneho OIDC prihlaseni
 */
final class OidcAuthorizationTransaction
{
    public function __construct(
        private readonly string $providerKey,
        private readonly string $state,
        private readonly string $nonce,
        private readonly string $codeVerifier,
        private readonly int $issuedAt,
        private readonly ?string $intendedPath,
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

    public function isExpired(int $now, int $ttlSeconds): bool
    {
        return $this->issuedAt + $ttlSeconds < $now;
    }

    /** @return array{providerKey:string,state:string,nonce:string,codeVerifier:string,issuedAt:int,intendedPath:string|null} */
    public function toArray(): array
    {
        return [
            'providerKey' => $this->providerKey,
            'state' => $this->state,
            'nonce' => $this->nonce,
            'codeVerifier' => $this->codeVerifier,
            'issuedAt' => $this->issuedAt,
            'intendedPath' => $this->intendedPath,
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
        if (!is_string($providerKey) || !is_string($state) || !is_string($nonce) || !is_string($codeVerifier) || !is_int($issuedAt) || ($intendedPath !== null && !is_string($intendedPath))) {
            return null;
        }

        return new self($providerKey, $state, $nonce, $codeVerifier, $issuedAt, $intendedPath);
    }
}
