<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page;

use Closure;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;

/**
 * Registruje indexove a full-page editorove capability admin modulu
 */
final class ModulePageRegistry
{
    /** @var array<string, ModuleIndexPageProviderInterface|Closure():ModuleIndexPageProviderInterface> */
    private array $indexProviders = [];

    /** @var array<string, ModuleEditorPageProviderInterface|Closure():ModuleEditorPageProviderInterface> */
    private array $editorProviders = [];

    /** @var array<string, ModuleModalEditorPageProviderInterface|Closure():ModuleModalEditorPageProviderInterface> */
    private array $modalEditorProviders = [];

    /**
     * Registruje indexovou stranku pod kodem modulu
     */
    public function registerIndex(string $moduleCode, ModuleIndexPageProviderInterface $provider): void
    {
        $this->indexProviders[$moduleCode] = $provider;
    }

    /**
     * Registruje lazy factory indexove stranky pro optional modul
     *
     * @param Closure():ModuleIndexPageProviderInterface $provider
     */
    public function registerIndexFactory(string $moduleCode, Closure $provider): void
    {
        $this->indexProviders[$moduleCode] = $provider;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovanou indexovou stranku
     */
    public function hasIndex(string $moduleCode): bool
    {
        return isset($this->indexProviders[$moduleCode]);
    }

    /**
     * Vraci indexovou stranku zaregistrovanou pod kodem modulu
     */
    public function index(string $moduleCode): ModuleIndexPageProviderInterface
    {
        $provider = $this->indexProviders[$moduleCode];
        if ($provider instanceof Closure) {
            $provider = $provider();
            $this->indexProviders[$moduleCode] = $provider;
        }

        return $provider;
    }

    /**
     * Registruje full-page editor pod kodem modulu
     */
    public function registerEditor(string $moduleCode, ModuleEditorPageProviderInterface $provider): void
    {
        $this->editorProviders[$moduleCode] = $provider;
    }

    /**
     * Registruje lazy factory full-page editoru pro optional modul
     *
     * @param Closure():ModuleEditorPageProviderInterface $provider
     */
    public function registerEditorFactory(string $moduleCode, Closure $provider): void
    {
        $this->editorProviders[$moduleCode] = $provider;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovany full-page editor
     */
    public function hasEditor(string $moduleCode): bool
    {
        return isset($this->editorProviders[$moduleCode]);
    }

    /**
     * Vraci full-page editor zaregistrovany pod kodem modulu
     */
    public function editor(string $moduleCode): ModuleEditorPageProviderInterface
    {
        $provider = $this->editorProviders[$moduleCode];
        if ($provider instanceof Closure) {
            $provider = $provider();
            $this->editorProviders[$moduleCode] = $provider;
        }

        return $provider;
    }

    /**
     * Registruje modalni editor pod kodem modulu
     */
    public function registerModalEditor(string $moduleCode, ModuleModalEditorPageProviderInterface $provider): void
    {
        $this->modalEditorProviders[$moduleCode] = $provider;
    }

    /**
     * Registruje lazy factory modalniho editoru pro optional modul
     *
     * @param Closure():ModuleModalEditorPageProviderInterface $provider
     */
    public function registerModalEditorFactory(string $moduleCode, Closure $provider): void
    {
        $this->modalEditorProviders[$moduleCode] = $provider;
    }

    /**
     * Rozhoduje, zda ma modul zaregistrovany modalni editor
     */
    public function hasModalEditor(string $moduleCode): bool
    {
        return isset($this->modalEditorProviders[$moduleCode]);
    }

    /**
     * Vraci modalni editor zaregistrovany pod kodem modulu
     */
    public function modalEditor(string $moduleCode): ModuleModalEditorPageProviderInterface
    {
        $provider = $this->modalEditorProviders[$moduleCode];
        if ($provider instanceof Closure) {
            $provider = $provider();
            $this->modalEditorProviders[$moduleCode] = $provider;
        }

        return $provider;
    }
}
