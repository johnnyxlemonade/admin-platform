<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\System\Languages\Editor\LanguageEditorDraft;
use PHPUnit\Framework\TestCase;

final class LanguageEditorDraftTest extends TestCase
{
    public function testItProvidesTheTypedInactiveCreateDefaults(): void
    {
        self::assertSame([
            'id' => null,
            'code' => '',
            'name' => '',
            'flag_code' => '',
            'enabled' => 0,
            'is_default' => 0,
            'sort_order' => 0,
        ], (new LanguageEditorDraft())->values());
    }
}
