<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Popisuje jednu autorizovanou file usage modulu bez persistence detailu
 */
final readonly class AdminFileUsageDefinition
{
    /**
     * Nastavuje modulovou usage, druh souboru a presentation capability
     */
    public function __construct(
        private string $moduleCode,
        private string $usage,
        private string $kind,
        private string $profile,
        private bool $multiple,
        private bool $sortable,
        private AdminFileUploadPresentation $presentation = AdminFileUploadPresentation::Standard,
        private ?string $imageProfile = null,
    ) {
        if ($this->imageProfile !== null && $this->kind !== 'file') {
            throw new \InvalidArgumentException('Only file usages may define an image profile.');
        }
    }

    /**
     * Vraci kod modulu vlastniciho usage
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vraci stabilni kod business usage
     */
    public function usage(): string
    {
        return $this->usage;
    }

    /**
     * Vraci image nebo file kind
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * Vraci framework upload profile
     */
    public function profile(): string
    {
        return $this->profile;
    }

    /**
     * Vraci volitelny image profil pro polymorfni generic file usage
     */
    public function imageProfile(): ?string
    {
        return $this->imageProfile;
    }

    /**
     * Urcuje zda target muze obsahovat vice souboru
     */
    public function multiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Urcuje zda kolekce prijima reorder
     */
    public function sortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Vraci shared editorovou presentation usage bez vlivu na transport
     */
    public function presentation(): AdminFileUploadPresentation
    {
        return $this->presentation;
    }
}
