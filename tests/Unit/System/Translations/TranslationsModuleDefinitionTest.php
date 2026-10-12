<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Translations;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\System\Translations\TranslationsModuleDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje deklaraci auditnich udalosti runtime override prekladu
 */
final class TranslationsModuleDefinitionTest extends TestCase
{
    public function testItDeclaresPresentationsForAllEmittedTranslationEvents(): void
    {
        $definitions = (new TranslationsModuleDefinition())->auditEventDefinitions();

        self::assertSame([
            'override_created',
            'override_updated',
            'override_removed',
        ], array_map(static fn(AuditEventDefinition $definition): string => $definition->suffix, $definitions));
    }
}
