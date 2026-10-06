<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page\Contract;

use Lemonade\Admin\Editor\EditorLoaded;
use Lemonade\Admin\Page\ModulePage;

/**
 * Vytvari modalni create a edit presentation poskytovanou admin modulem
 */
interface ModuleModalEditorPageProviderInterface
{
    /**
     * Vytvari modalni create presentation z pripravenych editorovych dat
     */
    public function modalCreate(EditorLoaded $editor, string $locale): ModulePage;

    /**
     * Vytvari modalni edit presentation z nactenych editorovych dat
     */
    public function modalEdit(EditorLoaded $editor, string $locale): ModulePage;
}
