<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Translations;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\System\Translations\Actions\TranslationsActionRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * Overuje registraci editacnich a resetovacich akci modulu prekladu
 */
final class TranslationsActionRegistrarTest extends TestCase
{
    /**
     * Overuje permission, potvrzeni a obnoveni gridu pro hromadny reset
     */
    public function testRegistersTheTranslationActionContract(): void
    {
        $actions = new ModuleActionRegistry();

        (new TranslationsActionRegistrar(
            actions: $actions,
        ))->register();

        $contract = [];
        foreach (['save', 'reset', 'bulk-reset'] as $key) {
            $definition = $actions->action('system.translations', $key)['definition'];
            $contract[$key] = [
                $definition->permission(),
                $definition->labelKey(),
                $definition->confirmation()?->messageKey(),
                $definition->refreshGrid(),
            ];
        }

        self::assertSame([
            'save' => ['system.translations.edit', 'admin.common.save', null, true],
            'reset' => ['system.translations.edit', 'translations.actions.reset', 'translations.confirm.reset', true],
            'bulk-reset' => ['system.translations.edit', 'translations.actions.bulk_reset', 'translations.confirm.bulk_reset', true],
        ], $contract);
    }
}
