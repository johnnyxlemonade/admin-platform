<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content\Action;

use DomainException;
use Lemonade\Admin\Action\Contract\ModuleActionAccessPolicyInterface;
use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Cms\Content\CmsContentLifecycle;
use Lemonade\Cms\Content\CmsContentReadModel;
use Lemonade\Framework\Http\HttpStatus;

/** Standardni auditovane akce nehierarchickeho CMS obsahu. */
final readonly class CmsContentAction implements ModuleActionHandlerInterface, ModuleActionAccessPolicyInterface
{
    /** @param CmsContentLifecycle<object> $lifecycle */
    public function __construct(
        private CmsContentLifecycle $lifecycle,
        private CmsContentReadModel $content,
        private LocalActorGuard $actors,
        private AuthorizationService $authorization,
        private CmsContentActionType $type,
        private string $permissionIdentity,
        private string $translationGroup,
    ) {}

    public function accessDecision(?int $id): bool
    {
        if ($id === null) {
            return false;
        }
        if ($this->type === CmsContentActionType::AssignOwner && !$this->authorization->hasPermission($this->permissionIdentity . '.assign_owner')) {
            return false;
        }
        $permission = match ($this->type) {
            CmsContentActionType::ChangeUrl => 'edit',
            CmsContentActionType::Delete => 'delete',
            CmsContentActionType::Restore => 'restore',
            CmsContentActionType::AssignOwner => 'view',
        };

        return $this->authorization->canForOwner(
            $this->permissionIdentity . '.' . $permission,
            $this->permissionIdentity . '.' . $permission . '_own',
            $this->content->ownerId($id),
        );
    }

    /** @param array<string,mixed> $payload */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        if ($id === null) {
            throw new ModuleActionException(HttpStatus::NOT_FOUND, AdminErrorCode::NOT_FOUND, ucfirst($this->translationGroup) . ' item not found.');
        }
        $locale = $payload['locale'] ?? null;
        $segment = $payload['url_segment'] ?? null;
        if ($this->type === CmsContentActionType::ChangeUrl && (!is_string($locale) || !is_string($segment))) {
            return ModuleActionResult::invalid(['url_segment' => 'admin.url_segment.invalid']);
        }
        $ownerId = filter_var($payload['owner_id'] ?? null, FILTER_VALIDATE_INT);
        if ($this->type === CmsContentActionType::AssignOwner && (!is_int($ownerId) || $ownerId < 1)) {
            return ModuleActionResult::invalid(['owner_id' => $this->translationGroup . '.validation.owner_invalid']);
        }

        try {
            $actor = $this->actors->requireLocalUser()->id();
            switch ($this->type) {
                case CmsContentActionType::ChangeUrl:
                    assert(is_string($locale) && is_string($segment));
                    $urlSegment = $this->lifecycle->changeUrlSegment($actor, $id, $locale, $segment);
                    return ModuleActionResult::success('admin.url_segment.updated', ['urlSegment' => $urlSegment]);
                case CmsContentActionType::Delete:
                    $this->lifecycle->delete($actor, $id);
                    return ModuleActionResult::success($this->translationGroup . '.actions.deleted');
                case CmsContentActionType::Restore:
                    $this->lifecycle->restore($actor, $id);
                    return ModuleActionResult::success($this->translationGroup . '.actions.restored');
                case CmsContentActionType::AssignOwner:
                    assert(is_int($ownerId));
                    $this->lifecycle->transferOwner($actor, $id, $ownerId);
                    return ModuleActionResult::success($this->translationGroup . '.actions.owner_assigned', refreshPage: true);
            }
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(HttpStatus::FORBIDDEN, AdminErrorCode::LOCAL_ACTOR_REQUIRED, $exception->getMessage());
        } catch (DomainException $exception) {
            $field = match ($this->type) {
                CmsContentActionType::ChangeUrl => 'url_segment',
                CmsContentActionType::AssignOwner => 'owner_id',
                default => '_form',
            };
            return ModuleActionResult::invalid([$field => $exception->getMessage()]);
        }
    }
}
