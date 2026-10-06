<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Popisuje nemennou konfiguraci admineditorheader
 */
final readonly class AdminEditorHeaderDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<array{label:string,labelKey?:string,href?:string,current?:bool}> $breadcrumbs
     * @param list<AdminEditorActionDefinition> $actions
     * @param list<string> $metadata
     */
    public function __construct(
        private string $title,
        private ?string $titleKey = null,
        private ?string $description = null,
        private ?string $descriptionKey = null,
        private array $breadcrumbs = [],
        private array $actions = [],
        private array $metadata = [],
        private ?AdminEditorHeaderContextDefinition $context = null,
    ) {}

    /**
     * Zpracovava hodnotu title v konfiguraci editoru
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Zpracovava hodnotu titlekey v konfiguraci editoru
     */
    public function titleKey(): ?string
    {
        return $this->titleKey;
    }

    /**
     * Zpracovava hodnotu description v konfiguraci editoru
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Zpracovava hodnotu descriptionkey v konfiguraci editoru
     */
    public function descriptionKey(): ?string
    {
        return $this->descriptionKey;
    }

    /**
     * Zpracovava hodnotu breadcrumbs v konfiguraci editoru
     * @return list<array{label:string,labelKey?:string,href?:string,current?:bool}>
     */
    public function breadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    /**
     * Zpracovava hodnotu actions v konfiguraci editoru
     * @return list<AdminEditorActionDefinition>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * Zpracovava hodnotu metadata v konfiguraci editoru
     * @return list<string>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Vrati volitelne prepinani kontextu hlavicky
     */
    public function context(): ?AdminEditorHeaderContextDefinition
    {
        return $this->context;
    }
}
