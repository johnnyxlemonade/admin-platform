<?php

declare(strict_types=1);

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var View $this
 * @var string $modalId
 * @var string $titleKey
 * @var string|null $title
 * @var string|null $descriptionKey
 * @var string|null $description
 * @var string|null $submitKey
 * @var AdminEditorDefinition $adminEditor
 * @var AdminEditorRenderContext $adminEditorContext
 * @var AdminEditorFieldRenderer $adminEditorFields
 */

$title = $title ?? $helpers->lang($titleKey);
$descriptionKey = $descriptionKey ?? null;
$description = $description ?? ($descriptionKey === null ? null : $helpers->lang($descriptionKey));
$submitKey = $submitKey ?? 'admin.common.save';
$form = $adminEditor->form();
?>
<header class="lm-modal-header">
    <div>
        <h2 class="lm-modal-title" id="<?= e($modalId) ?>-title" data-lemonade-modal-title data-lemonade-i18n="<?= e($titleKey) ?>"><?= e($title) ?></h2>
<?php if ($description !== null && $descriptionKey !== null): ?>        <p class="lm-modal-description" id="<?= e($modalId) ?>-description" data-lemonade-modal-description data-lemonade-i18n="<?= e($descriptionKey) ?>"><?= e($description) ?></p>
<?php endif; ?>    </div>
    <button class="lm-modal-close" type="button" data-lemonade-modal-close="close" aria-label="<?= e($helpers->lang('admin.common.close')) ?>" data-lemonade-i18n-aria-label="admin.common.close"></button>
</header>
<section class="lm-modal-body" data-lemonade-modal-body>
    <?= (new AdminEditorRenderer($this, $helpers, $adminEditorFields))->render($adminEditor, $adminEditorContext) ?>
</section>
<footer class="lm-modal-footer">
    <button class="btn btn-light" type="button" data-lemonade-modal-close="cancel" data-lemonade-i18n="admin.common.cancel"><?= e($helpers->lang('admin.common.cancel')) ?></button>
    <button class="btn btn-primary lm-button-primary" type="submit" form="<?= e($form->id()) ?>" data-lemonade-action data-lemonade-form="<?= e($form->id()) ?>" data-lemonade-url="<?= e((string) $form->actionUrl()) ?>" data-lemonade-method="<?= e($form->method()) ?>" data-lemonade-action-key="<?= e((string) $form->actionKey()) ?>" data-lemonade-i18n="<?= e($submitKey) ?>"><?= e($helpers->lang($submitKey)) ?></button>
</footer>
