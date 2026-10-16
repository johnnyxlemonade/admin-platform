<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\Page\ModulePageRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Overuje oddelene page capability v root katalogu
 */
final class DataGridIndexViewModelTest extends TestCase
{
    /**
     * Overuje nezavisle service ID vsech page provideru
     */
    public function testPageCapabilitiesAreRegisteredSeparately(): void
    {
        $registry = new ModulePageRegistry();
        $registry->registerIndex('cms.example', 'cms.example.index');
        $registry->registerEditor('cms.example', 'cms.example.editor');
        $registry->registerModalEditor('cms.example', 'cms.example.modal');

        self::assertSame('cms.example.index', $registry->indexProviderServiceId('cms.example'));
        self::assertSame('cms.example.editor', $registry->editorProviderServiceId('cms.example'));
        self::assertSame('cms.example.modal', $registry->modalEditorProviderServiceId('cms.example'));
    }
}
