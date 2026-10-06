<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\System\Notifications\NotificationsExportSelection;
use PHPUnit\Framework\TestCase;

/**
 * Overuje formularovy vyber pro export oznameni
 */
final class NotificationsExportSelectionTest extends TestCase
{
    /**
     * Overi prijeti unikatnich kladnych ID
     */
    public function testItAcceptsUniquePositiveIds(): void
    {
        self::assertSame([3, 9], NotificationsExportSelection::fromInput(['3', '9'])?->ids());
    }

    /**
     * Overi zamitnuti prazdneho nebo neplatneho vyberu
     */
    public function testItRejectsInvalidSelections(): void
    {
        foreach ([null, [], ['0'], ['-1'], ['a'], [3], ['3', '3'], array_fill(0, 101, '1')] as $input) {
            self::assertNull(NotificationsExportSelection::fromInput($input));
        }
    }
}
