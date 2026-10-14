<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use JsonException;

/**
 * Predava autorizovany image upload target ze serveru do rich-text komponenty
 */
final readonly class AdminRichTextImageUploadTarget
{
    /**
     * Nastavuje owner target a existujici chunk endpointy pro rich-text obrazky
     */
    public function __construct(
        private string $moduleCode,
        private int $entityId,
        private string $usage,
        private string $collectionUrl,
        private string $removeUrlTemplate,
        private string $startUrl,
        private string $appendUrlTemplate,
        private string $completeUrlTemplate,
        private string $abortUrlTemplate,
        private AdminFileUploadProfilePresentation $profile,
    ) {}

    /**
     * Serializuje explicitni target do bezpecneho data atributu editoru
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'module' => $this->moduleCode,
            'entity' => $this->entityId,
            'usage' => $this->usage,
            'kind' => 'image',
            'collectionUrl' => $this->collectionUrl,
            'removeUrlTemplate' => $this->removeUrlTemplate,
            'startUrl' => $this->startUrl,
            'appendUrlTemplate' => $this->appendUrlTemplate,
            'completeUrlTemplate' => $this->completeUrlTemplate,
            'abortUrlTemplate' => $this->abortUrlTemplate,
            'accept' => $this->profile->accept(),
            'allowedExtensions' => $this->profile->allowedExtensions(),
            'maxBytes' => $this->profile->maxBytes(),
            'maxSizeLabel' => $this->profile->maxSizeLabel(),
        ], JSON_THROW_ON_ERROR);
    }
}
