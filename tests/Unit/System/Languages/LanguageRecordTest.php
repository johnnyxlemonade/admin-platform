<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use PHPUnit\Framework\TestCase;

final class LanguageRecordTest extends TestCase
{
    public function testItConvertsDatabaseFlagsToTypedValues(): void
    {
        $record = LanguageRecord::fromRow([
            'id' => '7',
            'code' => 'cs',
            'name' => 'Čeština',
            'flag_code' => 'CZ',
            'enabled' => '1',
            'is_default' => 0,
            'sort_order' => '3',
        ]);

        self::assertSame(7, $record->id());
        self::assertSame('cs', $record->code());
        self::assertSame('Čeština', $record->name());
        self::assertSame('CZ', $record->flagCode());
        self::assertTrue($record->enabled());
        self::assertFalse($record->isDefault());
        self::assertSame(3, $record->sortOrder());
    }
}
