<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use InvalidArgumentException;

/**
 * Nese jednotnou presentation obrazku nebo fallbacku pro Admin rozhrani
 */
final readonly class AdminThumbnail
{
    /**
     * Nastavuje volitelnou image URL, textovou alternativu a vizualni presentation
     *
     * @param array{module:string,entity:string,usage:string,presentation:string}|null $fileIdentity
     */
    public function __construct(
        private ?string $url,
        private string $alt,
        private ?string $fallback,
        private string $size,
        private string $shape,
        private ?array $fileIdentity = null,
    ) {
        if (!in_array($size, ['compact', 'detail'], true)) {
            throw new InvalidArgumentException('Admin thumbnail size must be compact or detail.');
        }
        if (!in_array($shape, ['circle', 'rounded'], true)) {
            throw new InvalidArgumentException('Admin thumbnail shape must be circle or rounded.');
        }
    }

    /**
     * Vraci canonical URL obrazkove varianty nebo null pro fallback
     */
    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * Vraci alternativni text pro renderovany obrazek
     */
    public function alt(): string
    {
        return $this->alt;
    }

    /**
     * Vraci textovy fallback pri chybejicim obrazku
     */
    public function fallback(): ?string
    {
        return $this->fallback;
    }

    /**
     * Vraci velikost presentation odpovidajici gridu nebo detailu
     */
    public function size(): string
    {
        return $this->size;
    }

    /**
     * Vraci kruhovy nebo zaobleny tvar thumbnailu
     */
    public function shape(): string
    {
        return $this->shape;
    }

    /**
     * Vraci shared file identitu pro klientsky refresh souvisejicich thumbnailu
     *
     * @return array{module:string,entity:string,usage:string,presentation:string}|null
     */
    public function fileIdentity(): ?array
    {
        return $this->fileIdentity;
    }

    /**
     * Prevadi thumbnail na stabilni server-to-client presentation tvar
     *
     * @return array{url:string|null,alt:string,fallback:string|null,size:string,shape:string}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'alt' => $this->alt,
            'fallback' => $this->fallback,
            'size' => $this->size,
            'shape' => $this->shape,
        ];
    }
}
