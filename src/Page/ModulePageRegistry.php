<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page;

/**
 * Registruje indexove a full-page editorove capability admin modulu
 */
final class ModulePageRegistry
{
    /** @var array<string, string> */
    private array $indexProviders = [];

    /** @var array<string, string> */
    private array $editorProviders = [];

    /** @var array<string, string> */
    private array $modalEditorProviders = [];

    /**
     * Registruje service ID indexove stranky pod kodem modulu
     */
    public function registerIndex(string $moduleCode, string $providerServiceId): void
    {
        $this->indexProviders[$moduleCode] = $providerServiceId;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovanou indexovou stranku
     */
    public function hasIndex(string $moduleCode): bool
    {
        return isset($this->indexProviders[$moduleCode]);
    }

    /**
     * Vraci service ID indexove stranky zaregistrovane pod kodem modulu
     */
    public function indexProviderServiceId(string $moduleCode): string
    {
        return $this->indexProviders[$moduleCode];
    }

    /**
     * Registruje service ID full-page editoru pod kodem modulu
     */
    public function registerEditor(string $moduleCode, string $providerServiceId): void
    {
        $this->editorProviders[$moduleCode] = $providerServiceId;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovany full-page editor
     */
    public function hasEditor(string $moduleCode): bool
    {
        return isset($this->editorProviders[$moduleCode]);
    }

    /**
     * Vraci service ID full-page editoru zaregistrovaneho pod kodem modulu
     */
    public function editorProviderServiceId(string $moduleCode): string
    {
        return $this->editorProviders[$moduleCode];
    }

    /**
     * Registruje service ID modalniho editoru pod kodem modulu
     */
    public function registerModalEditor(string $moduleCode, string $providerServiceId): void
    {
        $this->modalEditorProviders[$moduleCode] = $providerServiceId;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovany modalni editor
     */
    public function hasModalEditor(string $moduleCode): bool
    {
        return isset($this->modalEditorProviders[$moduleCode]);
    }

    /**
     * Vraci service ID modalniho editoru zaregistrovaneho pod kodem modulu
     */
    public function modalEditorProviderServiceId(string $moduleCode): string
    {
        return $this->modalEditorProviders[$moduleCode];
    }
}
