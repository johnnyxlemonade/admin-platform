<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Nese z upload profilu odvozene udaje pro shared Admin upload UI
 */
final readonly class AdminFileUploadProfilePresentation
{
    /**
     * Nastavuje browser accept, zobrazene pripony a limit velikosti souboru
     *
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private string $accept,
        private array $allowedExtensions,
        private int $maxBytes,
        private string $maxSizeLabel,
    ) {}

    /**
     * Vraci browserovy accept atribut sestaveny z authoritativniho profilu
     */
    public function accept(): string
    {
        return $this->accept;
    }

    /**
     * Vraci pripony urcene k zobrazeni jako upload chipy
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Vraci limit velikosti v bytech pro pripadne klienty kontraktu
     */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Vraci lokalne nezavisly citelny limit velikosti
     */
    public function maxSizeLabel(): string
    {
        return $this->maxSizeLabel;
    }
}
