<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Nese shared presentation a endpointy pro jednu single nebo multiple file usage kolekci
 */
final readonly class AdminFileUploadCollection
{
    /**
     * @param list<array<string,mixed>> $files
     */
    public function __construct(
        private string $moduleCode,
        private int $entityId,
        private string $usage,
        private array $files,
        private string $startUrl,
        private string $appendUrlTemplate,
        private string $completeUrlTemplate,
        private string $abortUrlTemplate,
        private string $removeUrlTemplate,
        private string $reorderUrl,
        private string $renameModalUrlTemplate,
        private string $labelKey,
        private string $helpKey,
        private string $kind,
        private bool $multiple,
        private bool $sortable,
        private bool $editable,
        private AdminFileUploadProfilePresentation $profile,
        private AdminFileUploadPresentation $presentation,
        private ?string $fallback,
        private string $alt,
    ) {}

    /** @return list<array<string,mixed>> */
    public function files(): array
    {
        return $this->files;
    }

    public function startUrl(): string
    {
        return $this->startUrl;
    }

    public function appendUrlTemplate(): string
    {
        return $this->appendUrlTemplate;
    }

    public function completeUrlTemplate(): string
    {
        return $this->completeUrlTemplate;
    }

    public function abortUrlTemplate(): string
    {
        return $this->abortUrlTemplate;
    }

    public function removeUrlTemplate(): string
    {
        return $this->removeUrlTemplate;
    }

    public function reorderUrl(): string
    {
        return $this->reorderUrl;
    }

    /**
     * Vraci URL sablonu shared modalni akce pro prejmenovani jedne file identity
     */
    public function renameModalUrlTemplate(): string
    {
        return $this->renameModalUrlTemplate;
    }

    public function labelKey(): string
    {
        return $this->labelKey;
    }

    public function helpKey(): string
    {
        return $this->helpKey;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function multiple(): bool
    {
        return $this->multiple;
    }

    public function sortable(): bool
    {
        return $this->sortable;
    }

    public function editable(): bool
    {
        return $this->editable;
    }

    /**
     * Vraci presentation pravidla aktivniho framework upload profilu
     */
    public function profile(): AdminFileUploadProfilePresentation
    {
        return $this->profile;
    }

    /**
     * Určuje, zda collection renderuje lokalni single-image presentation
     */
    public function singleImage(): bool
    {
        return $this->kind === 'image' && !$this->multiple;
    }

    /**
     * Vraci variantu shared preview presentation
     */
    public function presentation(): AdminFileUploadPresentation
    {
        return $this->presentation;
    }

    /**
     * Vraci fallback zobrazovany pri chybejicim single image originalu
     */
    public function fallback(): ?string
    {
        return $this->fallback;
    }

    /**
     * Vraci alternativni text pro single image preview
     */
    public function alt(): string
    {
        return $this->alt;
    }

    /**
     * Vraci kompakni Admin thumbnail pro souvisejici editorovou summary
     */
    public function thumbnail(): AdminThumbnail
    {
        $file = $this->files[0] ?? null;
        $url = is_array($file) && is_string($file['preview_url'] ?? null)
            ? $file['preview_url']
            : null;

        return new AdminThumbnail(
            url: $url,
            alt: $this->alt,
            fallback: $this->fallback,
            size: 'detail',
            shape: $this->presentation === AdminFileUploadPresentation::Avatar ? 'circle' : 'rounded',
            fileIdentity: [
                'module' => $this->moduleCode,
                'entity' => (string) $this->entityId,
                'usage' => $this->usage,
                'presentation' => 'thumbnail',
            ],
        );
    }

    /**
     * Vraci kod modulu vlastniciho upload targetu
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vraci identifikator entity vlastniciho upload targetu
     */
    public function entityId(): int
    {
        return $this->entityId;
    }

    /**
     * Vraci business usage upload targetu
     */
    public function usage(): string
    {
        return $this->usage;
    }
}
