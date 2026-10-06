<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Actions;

use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Konfiguruje management handler pro deaktivaci volitelneho modulu
 */
final class ModulesDisableAction extends ModulesLifecycleAction
{
    /**
     * Predava operation canonical lifecycle service
     */
    public function __construct(
        ModuleStateResolver $states,
        ModuleLifecycleService $lifecycle,
        LocalActorGuard $actors,
    ) {
        parent::__construct('disable', $states, $lifecycle, $actors);
    }
}
