<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Sestavuje definici administracniho editoru po jednotlivych krocich
 */
final class AdminEditorBuilder
{
    private ?AdminEditorFormDefinition $form = null;
    private ?AdminEditorHeaderDefinition $header = null;
    /** @var list<AdminEditorBlock> */ private array $blocks = [];
    /** @var list<AdminEditorTab> */ private array $tabs = [];
    private ?AdminEditorSidebarDefinition $sidebar = null;
    private ?AdminEditorSaveBarDefinition $saveBar = null;
    /** @var array<string, scalar|bool|null> */ private array $renderMetadata = [];

    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    private function __construct(private readonly string $id)
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Admin editor ID must not be empty.');
        }
    }

    /**
     * Vytvari novou instanci s predanym nastavenim
     */
    public static function create(string $id): self
    {
        return new self($id);
    }

    /**
     * Zpracovava hodnotu form v konfiguraci editoru
     */
    public function form(AdminEditorFormDefinition $form): self
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Zpracovava hodnotu header v konfiguraci editoru
     */
    public function header(AdminEditorHeaderDefinition $header): self
    {
        $this->header = $header;
        return $this;
    }

    /**
     * Zpracovava hodnotu block v konfiguraci editoru
     */
    public function block(AdminEditorBlock $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    /**
     * Zpracovava hodnotu section v konfiguraci editoru
     */
    public function section(SectionBlock $section): self
    {
        return $this->block($section);
    }

    /**
     * Zpracovava hodnotu tab v konfiguraci editoru
     */
    public function tab(AdminEditorTab $tab): self
    {
        $this->tabs[] = $tab;
        return $this;
    }

    /**
     * Zpracovava hodnotu sidebar v konfiguraci editoru
     */
    public function sidebar(AdminEditorSidebarDefinition $sidebar): self
    {
        $this->sidebar = $sidebar;
        return $this;
    }

    /**
     * Zpracovava hodnotu savebar v konfiguraci editoru
     */
    public function saveBar(AdminEditorSaveBarDefinition $saveBar): self
    {
        $this->saveBar = $saveBar;
        return $this;
    }

    /**
     * Pripravuje vystup editoru pro rendermetadata
     * @param array<string, scalar|bool|null> $metadata
     */
    public function renderMetadata(array $metadata): self
    {
        $this->renderMetadata = $metadata;
        return $this;
    }

    /**
     * Sestavuje konecnou definici editoru z nasbiranych casti
     */
    public function build(): AdminEditorDefinition
    {
        if ($this->form === null) {
            throw new InvalidArgumentException('Admin editor requires a form definition.');
        }

        return new AdminEditorDefinition($this->id, $this->form, $this->header, $this->blocks, $this->tabs, $this->sidebar, $this->saveBar, $this->renderMetadata);
    }
}
