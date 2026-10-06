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
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleException;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleService;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Adaptuje management action transport na canonical lifecycle operations
 */
abstract class ModulesLifecycleAction implements ModuleActionHandlerInterface
{
    /**
     * Nastavuje pozadovanou operation, resolver stavu a canonical lifecycle service
     */
    public function __construct(
        private readonly string $action,
        private readonly ModuleStateResolver $states,
        private readonly ModuleLifecycleService $lifecycle,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Overuje transportni identifikator a deleguje operation canonical lifecycle service
     *
     * @param array<string, mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        $code = $this->code($id);
        if ($code === null) {
            return ModuleActionResult::invalid(['module' => 'modules.errors.invalid_module']);
        }
        try {
            $actor = AuditActor::user($this->actors->requireLocalUser()->id());
            if ($this->action === 'install') {
                $this->lifecycle->install($code, $actor);
            } elseif ($this->action === 'enable') {
                $this->lifecycle->enable($code, $actor);
            } elseif ($this->action === 'disable') {
                $this->lifecycle->disable($code, $actor);
            } else {
                return ModuleActionResult::invalid(['module' => 'modules.errors.invalid_state']);
            }
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (ModuleLifecycleException) {
            return ModuleActionResult::invalid(['module' => 'modules.errors.invalid_state']);
        } catch (\Throwable) {
            return ModuleActionResult::invalid(['module' => 'modules.errors.install_failed']);
        }
        return ModuleActionResult::success('modules.actions.' . $this->action . 'ed', [], null, true);
    }

    /**
     * Mapuje synteticke ID action transportu na kod z aktualniho runtime prehledu
     */
    private function code(?int $id): ?string
    {
        foreach ($this->states->all() as $state) {
            if (abs(crc32($state->code())) === $id) {
                return $state->code();
            }
        }
        return null;
    }
}
