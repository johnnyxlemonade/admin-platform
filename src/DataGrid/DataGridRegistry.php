<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

/**
 * Registruje service ID poskytovatelu datovych tabulek
 */
final class DataGridRegistry
{
    /** @var array<string, string> */
    private array $providers = [];

    /**
     * Zaregistruje service ID poskytovatele gridu pro modul
     */
    public function register(string $moduleCode, string $providerServiceId): void
    {
        $this->providers[$moduleCode] = $providerServiceId;
    }

    /**
     * Urci zda ma modul zaregistrovany grid
     */
    public function has(string $moduleCode): bool
    {
        return isset($this->providers[$moduleCode]);
    }

    /**
     * Vrati service ID poskytovatele gridu modulu
     */
    public function providerServiceId(string $moduleCode): string
    {
        return $this->providers[$moduleCode];
    }

    /**
     * Vrati serazene kody modulu s gridem
     *
     * @return list<string>
     */
    public function moduleCodes(): array
    {
        $codes = array_keys($this->providers);
        sort($codes);

        return $codes;
    }
}
