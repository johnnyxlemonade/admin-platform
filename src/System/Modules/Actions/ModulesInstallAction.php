<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Actions;

use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Konfiguruje management handler pro instalaci volitelneho modulu
 */
final class ModulesInstallAction extends ModulesLifecycleAction
{
    /**
     * Predava operation canonical lifecycle service
     */
    public function __construct(
        ModuleStateResolver $states,
        ModuleLifecycleService $lifecycle,
        LocalActorGuard $actors,
    ) {
        parent::__construct('install', $states, $lifecycle, $actors);
    }
}
