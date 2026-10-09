<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var bool $hasProtectedAuthority
 */
?>
<?php if ($hasProtectedAuthority): ?>
    <p class="lm-form-help" data-lemonade-i18n="users.editor.higher_administration_required"><?= e($helpers->lang('users.editor.higher_administration_required')) ?></p>
<?php endif; ?>
<div>
    <p class="lm-form-help" data-lemonade-i18n="users.editor.mfa_manage_help"><?= e($helpers->lang('users.editor.mfa_manage_help')) ?></p>
    <a class="btn btn-outline-secondary" href="<?= e($helpers->url('admin.profile.mfa')) ?>" data-lemonade-i18n="users.editor.mfa_manage"><?= e($helpers->lang('users.editor.mfa_manage')) ?></a>
</div>
