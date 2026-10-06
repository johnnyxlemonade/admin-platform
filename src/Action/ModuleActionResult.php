<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action;

/**
 * Popisuje vysledek admin akce pro admin klienta
 */
final readonly class ModuleActionResult
{
    /**
     * Vytvori vysledek s daty, zpetnou vazbou a pripadnymi chybami
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    private function __construct(
        private bool $successful,
        private ?string $messageKey,
        private array $data,
        private ?bool $refreshGrid,
        private bool $refreshPage,
        private string $feedbackType,
        private array $errors,
        private array $input,
    ) {}

    /**
     * Vytvori uspesny vysledek pro admin klienta
     *
     * @param array<string, mixed> $data
     */
    public static function success(?string $messageKey = null, array $data = [], ?bool $refreshGrid = null, bool $refreshPage = false): self
    {
        return new self(true, $messageKey, $data, $refreshGrid, $refreshPage, 'success', [], []);
    }

    /**
     * Vytvori uspesny vysledek s informacni zpetnou vazbou
     *
     * @param array<string, mixed> $data
     */
    public static function info(string $messageKey, array $data = [], ?bool $refreshGrid = null): self
    {
        return new self(true, $messageKey, $data, $refreshGrid, false, 'info', [], []);
    }

    /**
     * Vytvori neuspesny vysledek s chybami validace
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    public static function invalid(array $errors, array $input = []): self
    {
        return new self(false, null, [], false, false, 'error', $errors, $input);
    }

    /**
     * Urci zda akce uspesne skoncila
     */
    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * Vrati prekladovy klic zpetne vazby pokud existuje
     */
    public function messageKey(): ?string
    {
        return $this->messageKey;
    }

    /**
     * Vrati data vysledku akce
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Urci zda se ma po akci obnovit tabulka
     */
    public function refreshGrid(): bool
    {
        return $this->refreshGrid ?? false;
    }

    /**
     * Urci zda se ma po akci obnovit stranka
     */
    public function refreshPage(): bool
    {
        return $this->refreshPage;
    }

    /**
     * Vrati typ zpetne vazby pro admin klienta
     */
    public function feedbackType(): string
    {
        return $this->feedbackType;
    }

    /**
     * Vrati chyby validace podle poli
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Vrati vstup vraceny po chybe validace
     *
     * @return array<string, mixed>
     */
    public function input(): array
    {
        return $this->input;
    }

    /**
     * Doplni nenastavenou obnovu tabulky z definice akce
     */
    public function withDefinitionDefaults(ModuleActionDefinition $definition): self
    {
        return new self(
            $this->successful,
            $this->messageKey,
            $this->data,
            $this->refreshGrid ?? $definition->refreshGrid(),
            $this->refreshPage,
            $this->feedbackType,
            $this->errors,
            $this->input,
        );
    }
}
