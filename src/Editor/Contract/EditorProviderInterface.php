<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Contract;

use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Definuje modulovy kontrakt pro nacitani, validaci a ukladani dat editoru
 */
interface EditorProviderInterface
{
    /**
     * Vrati pole a opravneni podporovana editorem
     */
    public function editorDefinition(): EditorDefinition;

    /**
     * Nacte data existujiciho zaznamu pro jeho upravu
     *
     * @return array<string, mixed>
     */
    public function updateData(int $id): array;

    /**
     * Vrati vychozi data pro vytvoreni noveho zaznamu
     *
     * @return array<string, mixed>
     */
    public function createData(): array;

    /**
     * Vrati validacni schema pro vytvoreni noveho zaznamu
     */
    public function createValidationSchema(): ValidationSchema;

    /**
     * Vrati validacni schema pro upravu existujiciho zaznamu
     */
    public function updateValidationSchema(int $id): ValidationSchema;

    /**
     * Upravi existujici zaznam podle validovanych dat
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): EditorSaveResult;

    /**
     * Vytvori novy zaznam z validovanych dat
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): EditorSaveResult;
}
