<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Closure;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;

/**
 * Uchovava registry provideru editoru podle kodu modulu
 */
final class EditorRegistry
{
    /** @var array<string, EditorProviderInterface|Closure():EditorProviderInterface> */
    private array $providers = [];

    /**
     * Registruje provider pod kodem modulu
     */
    public function register(string $moduleCode, EditorProviderInterface $provider): void
    {
        $this->providers[$moduleCode] = $provider;
    }

    /**
     * Registruje lazy factory editoru pro optional modul
     *
     * @param Closure():EditorProviderInterface $provider
     */
    public function registerFactory(string $moduleCode, Closure $provider): void
    {
        $this->providers[$moduleCode] = $provider;
    }

    /**
     * Rozhoduje, zda je pozadovana definice zaregistrovana
     */
    public function has(string $moduleCode): bool
    {
        return isset($this->providers[$moduleCode]);
    }

    /**
     * Vraci registrovany provider pro kod modulu
     */
    public function provider(string $moduleCode): EditorProviderInterface
    {
        $provider = $this->providers[$moduleCode];
        if ($provider instanceof Closure) {
            $provider = $provider();
            $this->providers[$moduleCode] = $provider;
        }

        return $provider;
    }

    /**
     * Zpracovava hodnotu modulecodes v konfiguraci editoru
     * @return list<string>
     */
    public function moduleCodes(): array
    {
        $codes = array_keys($this->providers);
        sort($codes);

        return $codes;
    }
}
