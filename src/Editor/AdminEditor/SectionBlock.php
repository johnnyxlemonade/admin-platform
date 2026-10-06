<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Zpracovava data potrebna pro administracni editor
 */
class SectionBlock implements AdminEditorBlock
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<AdminEditorBlock> $blocks
     */
    public function __construct(
        private readonly string $id,
        private readonly array $blocks,
        private readonly ?string $title = null,
        private readonly ?string $titleKey = null,
        private readonly ?string $description = null,
        private readonly ?string $descriptionKey = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Section ID must not be empty.');
        }
        foreach ($blocks as $block) {
            if (!$block instanceof AdminEditorBlock) {
                throw new InvalidArgumentException('Section blocks must implement AdminEditorBlock.');
            }
        }
    }

    /**
     * Zpracovava hodnotu id v konfiguraci editoru
     */
    public function id(): string
    {
        return $this->id;
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
     * Zpracovava hodnotu title v konfiguraci editoru
     */
    public function title(): ?string
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
}
