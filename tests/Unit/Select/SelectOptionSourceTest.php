<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Select;

use InvalidArgumentException;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use PHPUnit\Framework\TestCase;

final class SelectOptionSourceTest extends TestCase
{
    public function testItExposesStaticOptionsForCurrentServerRenderedSelects(): void
    {
        $active = new SelectOptionDefinition('active', 'Active');
        $inactive = new SelectOptionDefinition('inactive', 'Inactive');
        $source = new StaticSelectOptionSource([
            $active,
            $inactive,
        ]);

        self::assertSame([$active, $inactive], $source->options());
        self::assertSame(['active', 'inactive'], $source->values());
    }

    public function testItRejectsInvalidOptionSourceCombinations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EditorFieldDefinition(
            name: 'role',
            type: 'select',
            readOnly: false,
            permission: null,
        );
    }

    public function testItRejectsAnOptionSourceOnANonSelectField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EditorFieldDefinition(
            name: 'title',
            type: 'text',
            readOnly: false,
            permission: null,
            optionSource: new StaticSelectOptionSource([new SelectOptionDefinition('draft', 'Draft')]),
        );
    }
}
