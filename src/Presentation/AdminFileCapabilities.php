<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Deklaruje file capability modulu bez vystaveni transportnich detailu konzumentum
 */
final readonly class AdminFileCapabilities
{
    /**
     * @param AdminFilePresentation|null $thumbnail Presentation hlavniho obrazku nebo null
     */
    public function __construct(
        public ?AdminFilePresentation $thumbnail = null,
        public bool $gallery = false,
        public bool $attachments = false,
        public bool $richTextImages = false,
        public bool $richTextFiles = false,
    ) {}
}
