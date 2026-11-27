<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 */
?>
<section class="lm-user-profile-panel">
    <div class="lm-user-profile-section-heading">
        <i class="<?= e(AdminIcon::Sliders->cssClass()) ?>" aria-hidden="true"></i>
        <div>
            <h2 data-lemonade-i18n="users.profile.preferences.title"><?= e($helpers->lang('users.profile.preferences.title')) ?></h2>
            <p data-lemonade-i18n="users.profile.preferences.description"><?= e($helpers->lang('users.profile.preferences.description')) ?></p>
        </div>
    </div>

    <div class="alert alert-info" role="note" data-lemonade-i18n="users.profile.preferences.browser_notice"><?= e($helpers->lang('users.profile.preferences.browser_notice')) ?></div>

    <div class="lm-user-profile-preference">
        <div>
            <strong data-lemonade-i18n="users.profile.preferences.appearance"><?= e($helpers->lang('users.profile.preferences.appearance')) ?></strong>
            <span data-lemonade-i18n="users.profile.preferences.appearance_help"><?= e($helpers->lang('users.profile.preferences.appearance_help')) ?></span>
        </div>
        <div class="lm-user-profile-options lm-user-profile-options--theme" role="radiogroup" aria-label="<?= e($helpers->lang('users.profile.preferences.appearance')) ?>">
            <?php foreach ([['system', AdminIcon::Display], ['light', AdminIcon::Sun], ['dark', AdminIcon::MoonStars]] as [$value, $icon]): ?>
                <button type="button" role="radio" aria-checked="false" data-lemonade-theme-option="<?= e($value) ?>">
                    <i class="<?= e($icon->cssClass()) ?>" aria-hidden="true"></i>
                    <span data-lemonade-i18n="admin.theme.<?= e($value) ?>"><?= e($helpers->lang('admin.theme.' . $value)) ?></span>
                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lm-user-profile-preference">
        <div>
            <strong data-lemonade-i18n="users.profile.preferences.language"><?= e($helpers->lang('users.profile.preferences.language')) ?></strong>
            <span data-lemonade-i18n="users.profile.preferences.language_help"><?= e($helpers->lang('users.profile.preferences.language_help')) ?></span>
        </div>
        <div class="lm-user-profile-options" role="group" aria-label="<?= e($helpers->lang('users.profile.preferences.language')) ?>">
            <button type="button" data-lemonade-locale-option="cs" data-lemonade-intl-locale="cs-CZ">
                <span class="lm-locale-flag lm-locale-flag--cs" aria-hidden="true"></span>
                <span data-lemonade-i18n="admin.account.czech"><?= e($helpers->lang('admin.account.czech')) ?></span>
                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-locale-active aria-hidden="true" hidden></i>
            </button>
            <button type="button" data-lemonade-locale-option="en" data-lemonade-intl-locale="en-GB">
                <span class="lm-locale-flag lm-locale-flag--en" aria-hidden="true"></span>
                <span data-lemonade-i18n="admin.account.english"><?= e($helpers->lang('admin.account.english')) ?></span>
                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-locale-active aria-hidden="true" hidden></i>
            </button>
        </div>
    </div>

    <div class="lm-user-profile-preference">
        <div>
            <strong data-lemonade-i18n="users.profile.preferences.sidebar"><?= e($helpers->lang('users.profile.preferences.sidebar')) ?></strong>
            <span data-lemonade-i18n="users.profile.preferences.sidebar_help"><?= e($helpers->lang('users.profile.preferences.sidebar_help')) ?></span>
        </div>
        <div class="lm-user-profile-options" role="radiogroup" aria-label="<?= e($helpers->lang('users.profile.preferences.sidebar')) ?>">
            <button type="button" role="radio" aria-checked="false" data-lemonade-sidebar-option="expanded">
                <i class="<?= e(AdminIcon::ListNested->cssClass()) ?>" aria-hidden="true"></i>
                <span data-lemonade-i18n="users.profile.preferences.sidebar_expanded"><?= e($helpers->lang('users.profile.preferences.sidebar_expanded')) ?></span>
                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-sidebar-check aria-hidden="true" hidden></i>
            </button>
            <button type="button" role="radio" aria-checked="false" data-lemonade-sidebar-option="collapsed">
                <i class="<?= e(AdminIcon::ChevronBarLeft->cssClass()) ?>" aria-hidden="true"></i>
                <span data-lemonade-i18n="users.profile.preferences.sidebar_collapsed"><?= e($helpers->lang('users.profile.preferences.sidebar_collapsed')) ?></span>
                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-sidebar-check aria-hidden="true" hidden></i>
            </button>
        </div>
    </div>
</section>
