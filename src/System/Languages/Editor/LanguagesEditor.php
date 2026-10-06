<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Editor;

use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Propojuje editorovy transport s create a update odpovednostmi service
 */
final class LanguagesEditor implements EditorProviderInterface
{
    /**
     * Nastavuje service pro mutace a schema pro vstupni validaci
     */
    public function __construct(
        private readonly LanguageService $languages,
        private readonly LanguagesEditorValidationSchema $validation,
    ) {}

    /**
     * Deklaruje prava editoru a read-only stav mimo samostatne akce
     */
    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'system.languages.edit',
            savePermission: 'system.languages.edit',
            createPermission: 'system.languages.create',
            fields: [
                new EditorFieldDefinition(name: 'code', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'name', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'flag_code', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'enabled', type: 'boolean', readOnly: true, permission: null),
                new EditorFieldDefinition(name: 'sort_order', type: 'number', readOnly: false, permission: null),
            ],
        );
    }

    /**
     * Nacte existujici zaznam pro editaci a mapuje chybejici zaznam na editorovou chybu
     *
     * @return array{language:LanguageRecord}
     */
    public function updateData(int $id): array
    {
        try {
            return ['language' => $this->languages->detail($id)];
        } catch (\RuntimeException $exception) {
            throw new EditorEntityNotFoundException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Poskytuje prazdny draft pro create editor
     *
     * @return array{language:LanguageEditorDraft}
     */
    public function createData(): array
    {
        return ['language' => new LanguageEditorDraft()];
    }

    /**
     * Pouzije schema bez code, protoze update business klic nemeni
     */
    public function updateValidationSchema(int $id): ValidationSchema
    {
        return $this->validation->forUpdate();
    }

    /**
     * Pouzije schema vcetne povinneho business klice code
     */
    public function createValidationSchema(): ValidationSchema
    {
        return $this->validation->forCreate();
    }

    /**
     * Uklada pouze editovatelne udaje bez zmeny code a stavu
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): EditorSaveResult
    {
        try {
            $this->languages->update(
                $id,
                trim((string) $data['name']),
                (string) $data['flag_code'],
                (int) $data['sort_order'],
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

        return new EditorSaveResult([], 'languages.editor.saved');
    }

    /**
     * Vytvari jazyk vzdy jako neaktivni, jeho stav se meni samostatnou akci
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): EditorSaveResult
    {
        try {
            $languageId = $this->languages->create(
                trim((string) $data['code']),
                trim((string) $data['name']),
                (string) $data['flag_code'],
                false,
                (int) $data['sort_order'],
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

        return new EditorSaveResult(['id' => $languageId], 'languages.editor.created');
    }
}
