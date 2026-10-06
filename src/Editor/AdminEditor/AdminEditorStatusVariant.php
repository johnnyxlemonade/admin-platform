<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Vymezuje podporovane varianty pro admineditorstatusvariant
 */
enum AdminEditorStatusVariant: string
{
    case Success = 'success';
    case Muted = 'muted';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
}
