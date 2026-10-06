<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Actions;

use Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface;
use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionResult;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Localization\TranslationOverrideService;
use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use RuntimeException;

/**
 * Obnovuje vybrane source preklady odstranenim jejich existujicich override hodnot
 */
final class TranslationsBulkResetAction implements ModuleActionHandlerInterface
{
    private const MAXIMUM_BATCH_SIZE = 100;

    /**
     * Nastavuje source katalog a canonical service pro auditovane odstraneni hodnot
     */
    public function __construct(
        private readonly TranslationsCatalog $catalog,
        private readonly TranslationOverrideService $overrides,
    ) {}

    /**
     * Overuje vyber a obnovuje jen identity s aktualne ulozenym override
     *
     * @param array<string,mixed> $payload
     */
    public function execute(?int $id, array $payload): ModuleActionResult
    {
        unset($id);

        try {
            $ids = $this->ids($payload);
            $translations = $this->catalog->findMany($ids);
            if (count($translations) !== count($ids)) {
                return ModuleActionResult::invalid(['_form' => 'translations.validation.bulk_ids_invalid']);
            }
            $this->overrides->removeMany(array_values(array_map(
                static fn(array $translation): array => [
                    'locale' => $translation['locale'],
                    'group' => $translation['group'],
                    'key' => $translation['key'],
                ],
                $translations,
            )));
        } catch (LocalActorRequiredException $exception) {
            throw new ModuleActionException(HttpStatusCode::FORBIDDEN, AdminErrorCode::LOCAL_ACTOR_REQUIRED, $exception->getMessage());
        } catch (RuntimeException $exception) {
            return ModuleActionResult::invalid(['_form' => $exception->getMessage()]);
        }

        return ModuleActionResult::success('translations.actions.bulk_reset');
    }

    /**
     * Normalizuje jedinecny a omezeny vyber DataGridu
     *
     * @param array<string,mixed> $payload
     * @return list<int>
     */
    private function ids(array $payload): array
    {
        $ids = $payload['ids'] ?? null;
        if (!is_array($ids) || $ids === [] || count($ids) > self::MAXIMUM_BATCH_SIZE) {
            throw new RuntimeException('translations.validation.bulk_ids_invalid');
        }
        foreach ($ids as $selectedId) {
            if (!is_int($selectedId) || $selectedId < 1) {
                throw new RuntimeException('translations.validation.bulk_ids_invalid');
            }
        }
        if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            throw new RuntimeException('translations.validation.bulk_ids_invalid');
        }

        return array_values($ids);
    }
}
