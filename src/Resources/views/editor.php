<?php

declare(strict_types=1);

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var View $this
 * @var AdminEditorDefinition $adminEditor
 * @var AdminEditorRenderContext $adminEditorContext
 * @var AdminEditorFieldRenderer $adminEditorFields
 * @var ViewHelpers $helpers
 */
?>
<?= (new AdminEditorRenderer($this, $helpers, $adminEditorFields))->render($adminEditor, $adminEditorContext) ?>
