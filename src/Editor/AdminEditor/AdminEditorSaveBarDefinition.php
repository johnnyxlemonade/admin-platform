<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Popisuje nemennou konfiguraci admineditorsavebar
 */
final readonly class AdminEditorSaveBarDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private AdminEditorActionDefinition $primaryAction,
        private ?AdminEditorActionDefinition $secondaryAction = null,
        private ?string $title = null,
        private ?string $description = null,
        private ?string $titleKey = null,
        private ?string $descriptionKey = null,
    ) {}

    /**
     * Zpracovava hodnotu primaryaction v konfiguraci editoru
     */
    public function primaryAction(): AdminEditorActionDefinition
    {
        return $this->primaryAction;
    }

    /**
     * Zpracovava hodnotu secondaryaction v konfiguraci editoru
     */
    public function secondaryAction(): ?AdminEditorActionDefinition
    {
        return $this->secondaryAction;
    }

    /**
     * Zpracovava hodnotu title v konfiguraci editoru
     */
    public function title(): ?string
    {
        return $this->title;
    }

    /**
     * Zpracovava hodnotu description v konfiguraci editoru
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Zpracovava hodnotu titlekey v konfiguraci editoru
     */
    public function titleKey(): ?string
    {
        return $this->titleKey;
    }

    /**
     * Zpracovava hodnotu descriptionkey v konfiguraci editoru
     */
    public function descriptionKey(): ?string
    {
        return $this->descriptionKey;
    }
}
