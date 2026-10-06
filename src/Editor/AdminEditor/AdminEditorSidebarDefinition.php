<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Popisuje nemennou konfiguraci admineditorsidebar
 */
final readonly class AdminEditorSidebarDefinition
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<SectionBlock> $panels
     */
    public function __construct(private array $panels)
    {
        foreach ($panels as $panel) {
            if (!$panel instanceof SectionBlock) {
                throw new \InvalidArgumentException('Sidebar panels must be SectionBlock instances.');
            }
        }
    }

    /**
     * Zpracovava hodnotu panels v konfiguraci editoru
     * @return list<SectionBlock>
     */
    public function panels(): array
    {
        return $this->panels;
    }
}
