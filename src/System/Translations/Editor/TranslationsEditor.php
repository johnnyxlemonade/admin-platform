<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Editor;

use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Localization\TranslationOverrideService;
use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Predava editorovy save transport do canonical translation override service
 */
final class TranslationsEditor implements EditorProviderInterface
{
    /**
     * Nastavuje katalog pro read-only identitu a service pro jedinou write cestu
     */
    public function __construct(
        private readonly TranslationsCatalog $catalog,
        private readonly TranslationOverrideService $overrides,
    ) {}

    /**
     * Deklaruje jen editovatelny override text vedle read-only source identity
     */
    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'system.translations.edit',
            savePermission: 'system.translations.edit',
            createPermission: 'system.translations.edit',
            fields: [
                new EditorFieldDefinition('locale', 'text', true, null),
                new EditorFieldDefinition('owner', 'text', true, null),
                new EditorFieldDefinition('group', 'text', true, null),
                new EditorFieldDefinition('key', 'text', true, null),
                new EditorFieldDefinition('source', 'textarea', true, null),
                new EditorFieldDefinition('effective', 'textarea', true, null),
                new EditorFieldDefinition('value', 'textarea', false, null),
            ],
        );
    }

    /**
     * Nacte aktualni source a effective hodnotu podle presentation identifikatoru
     *
     * @return array{translation:array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool}}
     */
    public function updateData(int $id): array
    {
        $translation = $this->catalog->find($id);
        if ($translation === null) {
            throw new EditorEntityNotFoundException('Translation source key not found.');
        }

        return ['translation' => $translation];
    }

    /**
     * Odmita nepodporeny create flow, protoze source translations nevznika v Adminu
     *
     * @return array<never,never>
     */
    public function createData(): array
    {
        return [];
    }

    /**
     * Povoli prazdnou hodnotu jako explicitni override
     */
    public function updateValidationSchema(int $id): ValidationSchema
    {
        unset($id);

        return ValidationSchema::create()->field('value', 'Override')->end();
    }

    /**
     * Odmita create validation, protoze modul neupravuje source katalog
     */
    public function createValidationSchema(): ValidationSchema
    {
        return ValidationSchema::create();
    }

    /**
     * Uklada override pres service a znovu odvodi identitu z aktualniho source katalogu
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): EditorSaveResult
    {
        $translation = $this->catalog->find($id);
        if ($translation === null) {
            throw new EditorEntityNotFoundException('Translation source key not found.');
        }
        try {
            $this->overrides->set(
                $translation['locale'],
                $translation['group'],
                $translation['key'],
                (string) ($data['value'] ?? ''),
            );
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            throw new EditorValidationException($exception->getMessage(), previous: $exception);
        }

        return new EditorSaveResult([], 'translations.editor.saved');
    }

    /**
     * Odmita create, protoze source hodnoty zustavaji read-only
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): EditorSaveResult
    {
        unset($data);
        throw new EditorValidationException('translations.validation.create_not_supported');
    }
}
