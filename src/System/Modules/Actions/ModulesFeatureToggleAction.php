<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Modules\Feature\ModuleFeatureLifecycleService;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleException;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Predava zmenu configured stavu volitelne feature canonical runtime service
 */
final class ModulesFeatureToggleAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje resolver stavu, canonical feature lifecycle a lokalniho aktora
     */
    public function __construct(
        private readonly ModuleStateResolver $states,
        private readonly ModuleFeatureLifecycleService $features,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Overuje management payload a deleguje mutaci configured stavu runtime service
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        $moduleCode = $payload['module_code'] ?? null;
        $featureCode = $payload['feature_code'] ?? null;
        $enabled = $payload['enabled'] ?? null;
        if (!is_string($moduleCode) || $moduleCode === '' || !is_string($featureCode) || $featureCode === '' || !is_bool($enabled)) {
            return ModuleActionResult::invalid(['feature' => 'modules.errors.invalid_feature']);
        }

        $target = $this->states->state($moduleCode);
        if ($id === null || abs(crc32($moduleCode)) !== $id || $target->missingCode() || !$target->available()) {
            return ModuleActionResult::invalid(['feature' => 'modules.errors.invalid_feature']);
        }

        try {
            $this->features->setEnabled(
                $moduleCode,
                $featureCode,
                $enabled,
                AuditActor::user($this->actors->requireLocalUser()->id()),
            );
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (ModuleLifecycleException) {
            return ModuleActionResult::invalid(['feature' => 'modules.errors.invalid_feature']);
        }

        return ModuleActionResult::success(
            $enabled ? 'modules.features.actions.enabled' : 'modules.features.actions.disabled',
            [],
            false,
            true,
        );
    }
}
