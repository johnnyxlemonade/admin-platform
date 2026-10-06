<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use Closure;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;

/**
 * Registruje poskytovatele datovych tabulek
 */
final class DataGridRegistry
{
    /** @var array<string, DataGridProviderInterface|Closure():DataGridProviderInterface> */
    private array $providers = [];

    /**
     * Zaregistruje poskytovatele gridu pro modul
     */
    public function register(string $moduleCode, DataGridProviderInterface $provider): void
    {
        $this->providers[$moduleCode] = $provider;
    }

    /**
     * Registruje factory pro provider, ktery se muze vytvorit az pri pouziti modulu
     *
     * @param Closure():DataGridProviderInterface $provider
     */
    public function registerFactory(string $moduleCode, Closure $provider): void
    {
        $this->providers[$moduleCode] = $provider;
    }

    /**
     * Urci zda ma modul zaregistrovany grid
     */
    public function has(string $moduleCode): bool
    {
        return isset($this->providers[$moduleCode]);
    }

    /**
     * Vrati poskytovatele gridu modulu
     */
    public function provider(string $moduleCode): DataGridProviderInterface
    {
        $provider = $this->providers[$moduleCode];
        if ($provider instanceof Closure) {
            $provider = $provider();
            $this->providers[$moduleCode] = $provider;
        }

        return $provider;
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
