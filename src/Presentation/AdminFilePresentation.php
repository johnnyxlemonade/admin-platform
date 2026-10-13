<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

/**
 * Urcuje shared presentation ulozene file usage v Admin editoru
 */
enum AdminFilePresentation: string
{
    case Standard = 'standard';
    case Avatar = 'avatar';
    case Landscape = 'landscape';
}
