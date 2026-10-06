<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

/**
 * Popisuje data prenosu identity pres OIDC
 */
final readonly class OidcAuthorizationCallback
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    private function __construct(
        private ?string $code,
        private ?string $state,
        private ?string $error,
    ) {}

    /**
     * Vraci nebo zpracovava hodnotu fromquery pro overeni identity
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            self::value($query, 'code'),
            self::value($query, 'state'),
            self::value($query, 'error'),
        );
    }

    /**
     * Vraci nebo zpracovava hodnotu code pro overeni identity
     */
    public function code(): ?string
    {
        return $this->code;
    }

    /**
     * Vraci nebo zpracovava hodnotu state pro overeni identity
     */
    public function state(): ?string
    {
        return $this->state;
    }

    /**
     * Vraci nebo zpracovava hodnotu error pro overeni identity
     */
    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * Rozhoduje stav haserror
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Vraci nebo zpracovava hodnotu value pro overeni identity
     * @param array<string, mixed> $query
     */
    private static function value(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
