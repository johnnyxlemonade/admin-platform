<?php

declare(strict_types=1);

namespace Lemonade\Admin\File;

/**
 * Provides system media attached to a published CMS record
 */
interface PublishedMediaInterface
{
    /**
     * Returns the visible thumbnail image when one is available
     */
    public function getThumbnail(): ?FileMetadata;

    /**
     * Returns visible gallery images in their stored order
     *
     * @return list<FileMetadata>
     */
    public function getGallery(): array;

    /**
     * Returns visible attachments in their stored order
     *
     * @return list<FileMetadata>
     */
    public function getAttachments(): array;
}
