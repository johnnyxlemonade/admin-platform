<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var array<string, mixed> $user
 * @var array<string, string> $passwordErrors
 * @var bool $passwordChanged
 * @var string|null $passwordChangedAt
 * @var string|null $mfaEnabledAt
 */

$external = (int) ($user['has_external_identity'] ?? 0) === 1;
$mfaActive = (int) ($user['mfa_active'] ?? 0) === 1;
$dateTime = static fn(?string $value): ?string => $value === null || $value === '' ? null : str_replace(' ', 'T', $value);
?>
<section class="lm-user-profile-panel">
    <div class="lm-user-profile-section-heading">
        <i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i>
        <div>
            <h2 data-lemonade-i18n="users.profile.security.password_title"><?= e($helpers->lang('users.profile.security.password_title')) ?></h2>
            <p data-lemonade-i18n="users.profile.security.password_description"><?= e($helpers->lang('users.profile.security.password_description')) ?></p>
        </div>
    </div>

    <?php if ($external): ?>
        <div class="alert alert-info" role="status" data-lemonade-i18n="users.profile.security.external_password"><?= e($helpers->lang('users.profile.security.external_password')) ?></div>
    <?php else: ?>
        <?php if ($passwordChanged): ?>
            <div class="alert alert-success" role="status" data-lemonade-i18n="users.profile.security.password_changed"><?= e($helpers->lang('users.profile.security.password_changed')) ?></div>
        <?php endif; ?>

        <?php if (isset($passwordErrors['_form'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($passwordErrors['_form']) ?></div>
        <?php endif; ?>

        <form class="lm-user-profile-password-form" method="post" action="<?= e($helpers->url('admin.system.users.profile.password')) ?>">
            <?= $helpers->csrfField() ?>
            <div>
                <label class="form-label" for="profile-current-password" data-lemonade-i18n="users.profile.security.current_password"><?= e($helpers->lang('users.profile.security.current_password')) ?></label>
                <input class="form-control<?= isset($passwordErrors['current_password']) ? ' is-invalid' : '' ?>" id="profile-current-password" name="current_password" type="password" autocomplete="current-password" required>
                <?php if (isset($passwordErrors['current_password'])): ?>
                    <div class="invalid-feedback"><?= e($passwordErrors['current_password']) ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label class="form-label" for="profile-new-password" data-lemonade-i18n="users.profile.security.new_password"><?= e($helpers->lang('users.profile.security.new_password')) ?></label>
                <input class="form-control<?= isset($passwordErrors['new_password']) ? ' is-invalid' : '' ?>" id="profile-new-password" name="new_password" type="password" autocomplete="new-password" minlength="<?= UserPasswordPolicy::MIN_LENGTH ?>" required>
                <?php if (isset($passwordErrors['new_password'])): ?>
                    <div class="invalid-feedback"><?= e($passwordErrors['new_password']) ?></div>
                <?php endif; ?>
            </div>

            <div>
                <label class="form-label" for="profile-password-confirmation" data-lemonade-i18n="users.profile.security.confirm_password"><?= e($helpers->lang('users.profile.security.confirm_password')) ?></label>
                <input class="form-control<?= isset($passwordErrors['new_password_confirmation']) ? ' is-invalid' : '' ?>" id="profile-password-confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" minlength="<?= UserPasswordPolicy::MIN_LENGTH ?>" required>
                <?php if (isset($passwordErrors['new_password_confirmation'])): ?>
                    <div class="invalid-feedback"><?= e($passwordErrors['new_password_confirmation']) ?></div>
                <?php endif; ?>
            </div>

            <div class="lm-user-profile-security-footer">
                <span>
                    <span data-lemonade-i18n="users.profile.security.last_password_change"><?= e($helpers->lang('users.profile.security.last_password_change')) ?></span>:
                    <?php if (($changed = $dateTime($passwordChangedAt)) === null): ?>
                        <span data-lemonade-i18n="users.profile.value_unknown"><?= e($helpers->lang('users.profile.value_unknown')) ?></span>
                    <?php else: ?>
                        <time datetime="<?= e($changed) ?>" data-lemonade-i18n-datetime><?= e((string) $passwordChangedAt) ?></time>
                    <?php endif; ?>
                </span>
                <button class="btn btn-primary lm-button-with-icon" type="submit">
                    <i class="<?= e(AdminIcon::Key->cssClass()) ?>" aria-hidden="true"></i>
                    <span data-lemonade-i18n="users.profile.security.change_password"><?= e($helpers->lang('users.profile.security.change_password')) ?></span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="lm-user-profile-panel">
    <div class="lm-user-profile-mfa-card">
        <span class="lm-user-profile-mfa-icon">
            <i class="<?= e(AdminIcon::ShieldLock->cssClass()) ?>" aria-hidden="true"></i>
        </span>
        <div class="lm-user-profile-mfa-content">
            <div class="lm-user-profile-mfa-title">
                <h2 data-lemonade-i18n="users.profile.security.mfa_title"><?= e($helpers->lang('users.profile.security.mfa_title')) ?></h2>
                <span class="badge <?= $mfaActive ? 'status-success' : 'status-muted' ?>">
                    <i class="<?= e(($mfaActive ? AdminIcon::ShieldCheck : AdminIcon::Shield)->cssClass()) ?>" aria-hidden="true"></i>
                    <span data-lemonade-i18n="<?= $mfaActive ? 'users.editor.mfa_active' : 'users.editor.mfa_inactive' ?>"><?= e($helpers->lang($mfaActive ? 'users.editor.mfa_active' : 'users.editor.mfa_inactive')) ?></span>
                </span>
            </div>
            <p data-lemonade-i18n="users.profile.security.mfa_description"><?= e($helpers->lang('users.profile.security.mfa_description')) ?></p>

            <?php if ($mfaActive): ?>
                <small>
                    <span data-lemonade-i18n="users.profile.security.mfa_enabled_at"><?= e($helpers->lang('users.profile.security.mfa_enabled_at')) ?></span>:
                    <?php if (($enabled = $dateTime($mfaEnabledAt)) === null): ?>
                        <span data-lemonade-i18n="users.profile.value_unknown"><?= e($helpers->lang('users.profile.value_unknown')) ?></span>
                    <?php else: ?>
                        <time datetime="<?= e($enabled) ?>" data-lemonade-i18n-datetime><?= e((string) $mfaEnabledAt) ?></time>
                    <?php endif; ?>
                </small>
            <?php else: ?>
                <small data-lemonade-i18n="users.profile.security.mfa_recommendation"><?= e($helpers->lang('users.profile.security.mfa_recommendation')) ?></small>
            <?php endif; ?>
        </div>
        <button class="btn btn-outline-secondary lm-button-with-icon" type="button" data-lemonade-modal-form-url="<?= e($helpers->url('admin.mfa.modal')) ?>" data-lemonade-modal-size="medium">
            <i class="<?= e(AdminIcon::ShieldLock->cssClass()) ?>" aria-hidden="true"></i>
            <span data-lemonade-i18n="users.editor.mfa_manage"><?= e($helpers->lang('users.editor.mfa_manage')) ?></span>
        </button>
    </div>
</section>
