<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Framework\Validation\ValidationSchema;
use PHPUnit\Framework\TestCase;

final class EditorRegistryTest extends TestCase
{
    public function testItRegistersOnlyModulesThatProvideAnEditorCapability(): void
    {
        $registry = new EditorRegistry();
        $provider = new class implements EditorProviderInterface {
            public function editorDefinition(): EditorDefinition
            {
                return new EditorDefinition(
                    loadPermission: 'cms.example.view',
                    savePermission: 'cms.example.edit',
                    createPermission: 'cms.example.create',
                    fields: [],
                );
            }

            public function updateData(int $id): array
            {
                return ['id' => $id];
            }

            public function createData(): array
            {
                return [];
            }

            public function updateValidationSchema(int $id): ValidationSchema
            {
                unset($id);

                return ValidationSchema::create();
            }

            public function createValidationSchema(): ValidationSchema
            {
                return ValidationSchema::create();
            }

            public function update(int $id, array $data): EditorSaveResult
            {
                unset($id, $data);

                return new EditorSaveResult([], 'cms.example.saved');
            }

            public function create(array $data): EditorSaveResult
            {
                unset($data);

                return new EditorSaveResult([], 'cms.example.created');
            }
        };

        $registry->register('cms.example', $provider);

        self::assertTrue($registry->has('cms.example'));
        self::assertFalse($registry->has('cms.without-editor'));
        self::assertSame($provider, $registry->provider('cms.example'));
    }
}
