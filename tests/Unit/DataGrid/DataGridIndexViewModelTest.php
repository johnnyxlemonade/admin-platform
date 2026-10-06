<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Page\Contract\ModuleEditorPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleIndexPageProviderInterface;
use Lemonade\Admin\Page\Contract\ModuleModalEditorPageProviderInterface;
use Lemonade\Admin\Page\ModulePage;
use Lemonade\Admin\Page\ModulePageRegistry;
use PHPUnit\Framework\TestCase;

final class DataGridIndexViewModelTest extends TestCase
{
    public function testCustomIndexProviderCanStillReturnItsOwnNamespacedView(): void
    {
        $provider = new class implements ModuleIndexPageProviderInterface {
            public function indexPermission(): string
            {
                return 'cms.example.view';
            }

            public function index(string $locale): ModulePage
            {
                return new ModulePage('example::index', 'Example', ['locale' => $locale]);
            }
        };
        $registry = new ModulePageRegistry();
        $registry->registerIndex('cms.example', $provider);

        self::assertSame($provider, $registry->index('cms.example'));
        self::assertSame('example::index', $registry->index('cms.example')->index('en')->view());
    }

    public function testFullPageEditorIsRegisteredSeparatelyFromIndexPage(): void
    {
        $provider = new class implements ModuleEditorPageProviderInterface {
            public function create(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
            {
                return new ModulePage('example::create', 'Create', ['locale' => $locale]);
            }

            public function editor(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage
            {
                return new ModulePage('example::editor', 'Edit', ['locale' => $locale]);
            }
        };
        $registry = new ModulePageRegistry();
        $registry->registerEditor('cms.example', $provider);

        self::assertTrue($registry->hasEditor('cms.example'));
        self::assertSame($provider, $registry->editor('cms.example'));
        self::assertFalse($registry->hasIndex('cms.example'));
    }

    /**
     * Overuje samostatnou registraci modalni presentation capability modulu
     */
    public function testModalEditorIsRegisteredSeparatelyFromFullPageEditor(): void
    {
        $provider = new class implements ModuleModalEditorPageProviderInterface {
            public function modalCreate(EditorLoaded $editor, string $locale): ModulePage
            {
                return new ModulePage('example::modal-create', 'Create', ['locale' => $locale]);
            }

            public function modalEdit(EditorLoaded $editor, string $locale): ModulePage
            {
                return new ModulePage('example::modal-edit', 'Edit', ['locale' => $locale]);
            }
        };
        $registry = new ModulePageRegistry();
        $registry->registerModalEditor('cms.example', $provider);

        self::assertTrue($registry->hasModalEditor('cms.example'));
        self::assertSame($provider, $registry->modalEditor('cms.example'));
        self::assertFalse($registry->hasEditor('cms.example'));
    }
}
