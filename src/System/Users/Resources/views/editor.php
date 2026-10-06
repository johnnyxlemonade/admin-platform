<?php

declare(strict_types=1);

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorRenderer;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var ClientTranslationVersion $clientTranslationVersion
 */
/**
 * @var View $this
 */
/**
 * @var AdminEditorDefinition $adminEditor
 */
/**
 * @var AdminEditorRenderContext $adminEditorContext
 */
?>
<div data-lemonade-i18n-namespace="users" data-lemonade-i18n-namespace-source="<?= e($helpers->url('admin.resources.i18n', ['group' => 'users'])) ?>?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="<?= e(json_encode($clientTranslationVersion->versions('users'), JSON_THROW_ON_ERROR)) ?>">
    <?= (new AdminEditorRenderer($this, $helpers))->render($adminEditor, $adminEditorContext) ?>
</div>
