<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

/**
 * Vymezuje vysledne stavy instalacniho kroku
 */
enum InstallStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Error = 'error';
    case Skipped = 'skipped';
}
