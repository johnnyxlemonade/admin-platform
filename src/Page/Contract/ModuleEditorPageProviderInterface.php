<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page\Contract;

use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Page\ModulePage;

/**
 * Urcuje full-page create a edit stranky poskytovane admin modulem
 */
interface ModuleEditorPageProviderInterface
{
    /**
     * Vytvari create stranku z pripravenych editorovych dat
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, mixed> $query
     */
    public function create(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage;

    /**
     * Vytvari edit stranku z nacteneho zaznamu editoru
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     * @param array<string, mixed> $query
     */
    public function editor(EditorLoaded $editor, array $errors, array $input, string $locale, array $query = []): ModulePage;
}
