<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Sklada vnorenou skupinu shared tabu uvnitr editoroveho obsahu
 */
final readonly class TabGroupBlock implements AdminEditorBlock
{
    /**
     * Nastavuje stabilni identitu a overuje vnitrni taby
     *
     * @param list<AdminEditorTab> $tabs
     */
    public function __construct(
        private string $id,
        private array $tabs,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Tab group ID must not be empty.');
        }
        if ($tabs === []) {
            throw new InvalidArgumentException('Tab group must contain at least one tab.');
        }

        $defaultTabs = 0;
        foreach ($tabs as $tab) {
            if (!$tab instanceof AdminEditorTab) {
                throw new InvalidArgumentException('Tab group items must be AdminEditorTab instances.');
            }
            if ($tab->default()) {
                ++$defaultTabs;
            }
        }
        if ($defaultTabs !== 1) {
            throw new InvalidArgumentException('Tab group must contain exactly one default tab.');
        }
    }

    /**
     * Vrati stabilni identitu vnorenych tabu
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Vrati deklarovane vnitrni taby
     *
     * @return list<AdminEditorTab>
     */
    public function tabs(): array
    {
        return $this->tabs;
    }
}
