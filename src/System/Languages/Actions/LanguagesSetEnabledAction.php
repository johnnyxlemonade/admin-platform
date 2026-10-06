<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Predava aktivaci nebo deaktivaci jazyka do service se spravnym action vysledkem
 */
final class LanguagesSetEnabledAction implements ModuleActionHandlerInterface
{
    public function __construct(private readonly LanguageService $languages, private readonly bool $enabled) {}

    /**
     * Vyzaduje cilovy zaznam a mapuje domainova odmitnuti na action response
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($payload);
        if ($id === null) {
            throw new ModuleActionException(
                HttpStatusCode::NOT_FOUND,
                AdminErrorCode::NOT_FOUND,
                'Language not found.',
            );
        }
        try {
            $this->languages->setEnabled($id, $this->enabled);
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return ModuleActionResult::success($this->enabled ? 'languages.actions.enabled' : 'languages.actions.disabled');
    }
}
