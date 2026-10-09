<?php

declare(strict_types=1);

use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
/**
 * @var bool $hasProtectedAuthority
 */
/**
 * @var bool $isSelf
 */
/**
 * @var bool $mfaActive
 */
/**
 * @var bool $canResetMfa
 */
/**
 * @var string $mfaModalUrl
 */
/**
 * @var string $mfaResetUrl
 */
?>
<?php if ($hasProtectedAuthority): ?>
    <p class="lm-form-help" data-lemonade-i18n="users.editor.higher_administration_required"><?= e($helpers->lang('users.editor.higher_administration_required')) ?></p>
<?php endif; ?>
<div>
    <p class="lm-form-help" data-lemonade-i18n="users.editor.two_factor"><?= e($helpers->lang('users.editor.two_factor')) ?></p>
    <p data-lemonade-i18n="<?= $mfaActive ? 'users.editor.mfa_active' : 'users.editor.mfa_inactive' ?>"><?= e($helpers->lang($mfaActive ? 'users.editor.mfa_active' : 'users.editor.mfa_inactive')) ?></p>
    <?php if ($isSelf): ?>
        <button class="btn btn-outline-secondary" type="button" data-lemonade-modal-form-url="<?= e($mfaModalUrl) ?>" data-lemonade-modal-size="medium" data-lemonade-i18n="users.editor.mfa_manage"><?= e($helpers->lang('users.editor.mfa_manage')) ?></button>
    <?php elseif ($canResetMfa): ?>
        <button class="btn btn-outline-danger" type="button" data-lemonade-action data-lemonade-url="<?= e($mfaResetUrl) ?>" data-lemonade-method="POST" data-lemonade-payload="<?= e(json_encode(['action' => 'mfa-reset', 'payload' => []], JSON_THROW_ON_ERROR)) ?>" data-lemonade-confirm="users.confirm.mfa_reset" data-lemonade-i18n="users.editor.mfa_reset"><?= e($helpers->lang('users.editor.mfa_reset')) ?></button>
    <?php endif; ?>
</div>
