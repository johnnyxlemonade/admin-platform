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
 * Predava zmenu vychoziho jazyka do service a mapuje jeji odmitnuti na action response
 */
final class LanguagesSetDefaultAction implements ModuleActionHandlerInterface
{
    public function __construct(private readonly LanguageService $languages) {}

    /**
     * Vyzaduje cilovy zaznam a propaguje validacni nebo local-actor odmitnuti
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
            $this->languages->setDefault($id);
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return ModuleActionResult::success('languages.actions.default_changed');
    }
}
