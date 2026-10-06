<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Určuje shared presentation ulozene file usage v Admin editoru
 */
enum AdminFileUploadPresentation: string
{
    case Standard = 'standard';
    case Avatar = 'avatar';
    case Landscape = 'landscape';
}
