<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

/**
 * Registruje service ID provideru editoru podle kodu modulu
 */
final class EditorRegistry
{
    /** @var array<string, string> */
    private array $providers = [];

    /**
     * Registruje service ID provideru pod kodem modulu
     */
    public function register(string $moduleCode, string $providerServiceId): void
    {
        $this->providers[$moduleCode] = $providerServiceId;
    }

    /**
     * Rozhoduje, zda je pozadovana definice zaregistrovana
     */
    public function has(string $moduleCode): bool
    {
        return isset($this->providers[$moduleCode]);
    }

    /**
     * Vraci registrovane service ID provideru pro kod modulu
     */
    public function providerServiceId(string $moduleCode): string
    {
        return $this->providers[$moduleCode];
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
