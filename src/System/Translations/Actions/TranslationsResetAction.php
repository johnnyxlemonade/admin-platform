<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Contract\ModuleActionLockBypassPolicyInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Localization\TranslationOverrideService;
use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;

/**
 * Odstranuje override a vraci runtime resolve na package source
 */
final class TranslationsResetAction implements ModuleActionHandlerInterface, ModuleActionLockBypassPolicyInterface
{
    /**
     * Nastavuje zdrojovou identitu a jedinou mutation service
     */
    public function __construct(
        private readonly TranslationsCatalog $catalog,
        private readonly TranslationOverrideService $overrides,
    ) {}

    /**
     * Odstrani override pro radek DataGridu bez druhe persistence cesty
     *
     * @param array<string,mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($payload);
        $translation = $id === null ? null : $this->catalog->find($id);
        if ($translation === null) {
            throw new ModuleActionException(HttpStatusCode::NOT_FOUND, AdminErrorCode::NOT_FOUND, 'Translation source key not found.');
        }
        try {
            $this->overrides->remove($translation['locale'], $translation['group'], $translation['key']);
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(HttpStatusCode::FORBIDDEN, AdminErrorCode::LOCAL_ACTOR_REQUIRED, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return ModuleActionResult::success('translations.actions.reset');
    }

    /**
     * Umoznuje reset z gridu, protoze identita neni editorovy zamek business entity
     *
     * @param array<string,mixed> $payload
     */
    public function canBypassForeignLock(?int $id, array $payload): bool
    {
        unset($id, $payload);

        return true;
    }
}
