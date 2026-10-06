<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Popisuje nemennou konfiguraci admineditor
 */
final readonly class AdminEditorDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<AdminEditorBlock> $blocks
     * @param list<AdminEditorTab> $tabs
     * @param array<string, scalar|bool|null> $renderMetadata
     */
    public function __construct(
        private string $id,
        private AdminEditorFormDefinition $form,
        private ?AdminEditorHeaderDefinition $header,
        private array $blocks,
        private array $tabs = [],
        private ?AdminEditorSidebarDefinition $sidebar = null,
        private ?AdminEditorSaveBarDefinition $saveBar = null,
        private array $renderMetadata = [],
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Admin editor ID must not be empty.');
        }
        foreach ($blocks as $block) {
            if (!$block instanceof AdminEditorBlock) {
                throw new InvalidArgumentException('Editor blocks must implement AdminEditorBlock.');
            }
        }
        if ($tabs !== [] && $blocks !== []) {
            throw new InvalidArgumentException('Editor cannot define root blocks and tabs together.');
        }

        $tabIds = [];
        $defaultTabs = 0;
        foreach ($tabs as $tab) {
            if (!$tab instanceof AdminEditorTab) {
                throw new InvalidArgumentException('Editor tabs must be AdminEditorTab instances.');
            }
            if (isset($tabIds[$tab->id()])) {
                throw new InvalidArgumentException('Editor tab IDs must be unique.');
            }
            $tabIds[$tab->id()] = true;
            $defaultTabs += $tab->default() ? 1 : 0;
        }
        if ($tabs !== [] && $defaultTabs !== 1) {
            throw new InvalidArgumentException('Tabbed editor must define exactly one default tab.');
        }

        $sectionBlocks = $blocks;
        foreach ($tabs as $tab) {
            $sectionBlocks = [...$sectionBlocks, ...$tab->blocks()];
        }
        if ($sidebar !== null) {
            $sectionBlocks = [...$sectionBlocks, ...$sidebar->panels()];
        }
        $this->assertUniqueSectionIds($sectionBlocks);
    }

    /**
     * Zpracovava hodnotu id v konfiguraci editoru
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Zpracovava hodnotu form v konfiguraci editoru
     */
    public function form(): AdminEditorFormDefinition
    {
        return $this->form;
    }

    /**
     * Zpracovava hodnotu header v konfiguraci editoru
     */
    public function header(): ?AdminEditorHeaderDefinition
    {
        return $this->header;
    }

    /**
     * Zpracovava hodnotu blocks v konfiguraci editoru
     * @return list<AdminEditorBlock>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * Zpracovava hodnotu tabs v konfiguraci editoru
     * @return list<AdminEditorTab>
     */
    public function tabs(): array
    {
        return $this->tabs;
    }

    /**
     * Zpracovava hodnotu sidebar v konfiguraci editoru
     */
    public function sidebar(): ?AdminEditorSidebarDefinition
    {
        return $this->sidebar;
    }

    /**
     * Zpracovava hodnotu savebar v konfiguraci editoru
     */
    public function saveBar(): ?AdminEditorSaveBarDefinition
    {
        return $this->saveBar;
    }

    /**
     * Pripravuje vystup editoru pro rendermetadata
     * @return array<string, scalar|bool|null>
     */
    public function renderMetadata(): array
    {
        return $this->renderMetadata;
    }

    /**
     * Overuje podminku assertuniquesectionids a pri poruseni vyhodi vyjimku
     * @param list<AdminEditorBlock> $blocks
     * @param array<string, true> $sectionIds
     */
    private function assertUniqueSectionIds(array $blocks, array &$sectionIds = []): void
    {
        foreach ($blocks as $block) {
            if (!$block instanceof SectionBlock) {
                continue;
            }
            if (isset($sectionIds[$block->id()])) {
                throw new InvalidArgumentException('Editor section and panel IDs must be unique.');
            }
            $sectionIds[$block->id()] = true;
            $this->assertUniqueSectionIds($block->blocks(), $sectionIds);
        }
    }
}
