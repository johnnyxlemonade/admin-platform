<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
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
<?php
$mfaStatusKey = $mfaActive ? 'users.editor.mfa_active' : 'users.editor.mfa_inactive';
$mfaStatusClass = $mfaActive ? 'status-success' : 'status-muted';
$mfaIcon = $mfaActive ? AdminIcon::ShieldCheck : AdminIcon::Shield;
?>
<div class="lm-access-mfa">
    <span class="badge <?= e($mfaStatusClass) ?> lm-access-mfa-status">
        <i class="<?= e($mfaIcon->cssClass()) ?>" aria-hidden="true"></i>
        <span data-lemonade-i18n="<?= e($mfaStatusKey) ?>"><?= e($helpers->lang($mfaStatusKey)) ?></span>
    </span>
    <?php if ($isSelf): ?>
        <button class="btn btn-outline-secondary lm-button-with-icon" type="button" data-lemonade-modal-form-url="<?= e($mfaModalUrl) ?>" data-lemonade-modal-size="medium">
            <i class="<?= e(AdminIcon::ShieldLock->cssClass()) ?>" aria-hidden="true"></i>
            <span data-lemonade-i18n="users.editor.mfa_manage"><?= e($helpers->lang('users.editor.mfa_manage')) ?></span>
        </button>
    <?php elseif ($canResetMfa): ?>
        <button class="btn btn-outline-secondary lm-button-with-icon" type="button" data-lemonade-action data-lemonade-url="<?= e($mfaResetUrl) ?>" data-lemonade-method="POST" data-lemonade-payload="<?= e(json_encode(['action' => 'mfa-reset', 'payload' => []], JSON_THROW_ON_ERROR)) ?>" data-lemonade-confirm="users.confirm.mfa_reset">
            <i class="<?= e(AdminIcon::ArrowCounterclockwise->cssClass()) ?>" aria-hidden="true"></i>
            <span data-lemonade-i18n="users.editor.mfa_reset"><?= e($helpers->lang('users.editor.mfa_reset')) ?></span>
        </button>
    <?php endif; ?>
</div>
