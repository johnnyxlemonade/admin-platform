<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use InvalidArgumentException;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBlock;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class AdminEditorTab
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<AdminEditorBlock> $blocks
     */
    public function __construct(
        private string $id,
        private string $label,
        private array $blocks,
        private ?string $labelKey = null,
        private bool $default = false,
        private ?string $badge = null,
    ) {
        if (trim($id) === '' || (trim($label) === '' && ($labelKey === null || trim($labelKey) === ''))) {
            throw new InvalidArgumentException('Tab ID and label must not be empty.');
        }
        foreach ($blocks as $block) {
            if (!$block instanceof AdminEditorBlock) {
                throw new InvalidArgumentException('Tab blocks must implement AdminEditorBlock.');
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
     * Zpracovava hodnotu label v konfiguraci editoru
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Zpracovava hodnotu labelkey v konfiguraci editoru
     */
    public function labelKey(): ?string
    {
        return $this->labelKey;
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
     * Zpracovava hodnotu default v konfiguraci editoru
     */
    public function default(): bool
    {
        return $this->default;
    }

    /**
     * Zpracovava hodnotu badge v konfiguraci editoru
     */
    public function badge(): ?string
    {
        return $this->badge;
    }
}
