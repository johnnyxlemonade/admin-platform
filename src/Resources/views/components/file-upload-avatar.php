<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Presentation\AdminFileUploadCollection;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var AdminFileUploadCollection $uploadCollection
 */

$file = $uploadCollection->files()[0] ?? null;
$hasFile = is_array($file);
$fileId = $hasFile ? (string) $file['id'] : '';
?>
<section class="lm-file-upload-avatar" data-lemonade-file-upload-collection data-lemonade-file-upload-avatar data-lemonade-file-upload-single="true" data-lemonade-file-upload-presentation="avatar" data-lemonade-file-upload-start-url="<?= e($uploadCollection->startUrl()) ?>" data-lemonade-file-upload-append-url="<?= e($uploadCollection->appendUrlTemplate()) ?>" data-lemonade-file-upload-complete-url="<?= e($uploadCollection->completeUrlTemplate()) ?>" data-lemonade-file-upload-abort-url="<?= e($uploadCollection->abortUrlTemplate()) ?>" data-lemonade-file-upload-remove-url="<?= e($uploadCollection->removeUrlTemplate()) ?>" data-lemonade-file-upload-reorder-url="<?= e($uploadCollection->reorderUrl()) ?>" data-lemonade-file-upload-rename-modal-url="<?= e($uploadCollection->renameModalUrlTemplate()) ?>" data-lemonade-file-upload-kind="<?= e($uploadCollection->kind()) ?>" data-lemonade-file-upload-multiple="false" data-lemonade-file-upload-allowed-extensions="<?= e(implode(',', $uploadCollection->profile()->allowedExtensions())) ?>" data-lemonade-file-upload-max-bytes="<?= e((string) $uploadCollection->profile()->maxBytes()) ?>" data-lemonade-file-upload-max-size-label="<?= e($uploadCollection->profile()->maxSizeLabel()) ?>" data-lemonade-file-upload-file-too-large-label="<?= e($helpers->lang('admin.file_upload.file_too_large', ['size' => $uploadCollection->profile()->maxSizeLabel()])) ?>" data-lemonade-file-upload-extension-not-allowed-label="<?= e($helpers->lang('admin.file_upload.extension_not_allowed')) ?>" data-lemonade-file-upload-failed-label="<?= e($helpers->lang('admin.file_upload.failed')) ?>" data-lemonade-file-upload-discard-label="<?= e($helpers->lang('admin.file_upload.remove_from_queue')) ?>" data-lemonade-file-upload-remove-key="users.editor.avatar_remove" data-lemonade-file-upload-remove-label="<?= e($helpers->lang('users.editor.avatar_remove')) ?>" data-lemonade-file-upload-retry-label="<?= e($helpers->lang('admin.file_upload.retry')) ?>">
    <svg class="lm-avatar-upload-progress" viewBox="0 0 120 120" aria-hidden="true" data-lemonade-file-upload-avatar-progress><circle cx="60" cy="60" r="56" pathLength="100" data-lemonade-file-upload-avatar-progress-ring></circle></svg>
    <span class="lm-file-upload-avatar-actions">
        <button class="btn btn-light btn-sm lm-file-upload-item-action" type="button" data-lemonade-file-upload-avatar-select aria-label="<?= e($helpers->lang('users.editor.avatar_upload')) ?>" title="<?= e($helpers->lang('users.editor.avatar_upload')) ?>" data-lemonade-i18n-aria-label="users.editor.avatar_upload" data-lemonade-i18n-title="users.editor.avatar_upload"><i class="<?= e(AdminIcon::PencilSquare->cssClass()) ?>" aria-hidden="true"></i></button>
        <button class="btn btn-light btn-sm lm-file-upload-item-action is-danger" type="button" data-lemonade-file-upload-avatar-remove data-lemonade-file-upload-avatar-file-id="<?= e($fileId) ?>"<?= $hasFile ? '' : ' hidden' ?> aria-label="<?= e($helpers->lang('users.editor.avatar_remove')) ?>" title="<?= e($helpers->lang('users.editor.avatar_remove')) ?>" data-lemonade-i18n-aria-label="users.editor.avatar_remove" data-lemonade-i18n-title="users.editor.avatar_remove"><i class="<?= e(AdminIcon::Trash3->cssClass()) ?>" aria-hidden="true"></i></button>
    </span>
    <input class="visually-hidden" type="file" accept="<?= e($uploadCollection->profile()->accept()) ?>" data-lemonade-file-upload-input>
    <div class="lm-file-upload-avatar-feedback" aria-live="polite" data-lemonade-file-upload-items></div>
    <span class="lm-form-help is-error" role="alert" hidden data-lemonade-file-upload-error></span>
</section>
