<?php

declare(strict_types=1);

use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Navigation\AdminNavigationEntryInterface;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Presentation\AdminThumbnail;
use Lemonade\Framework\View\RequestViewHelpers;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
/** @var RequestViewHelpers $requestHelpers */
/** @var View $this */
/** @var string $title */
/** @var string $locale */
/** @var list<AdminNavigationEntryInterface> $navigation */
/** @var ClientTranslationVersion $clientTranslationVersion */
/** @var AdminBranding $branding */
/** @var AdminAssetManifest $adminAssets */
/** @var AuthenticatedUser|null $currentUser */
/** @var AdminThumbnail $avatar */
/** @var string $content */

$locale = $locale ?? 'cs';
$sidebarStorageKey = 'lemonade-admin-sidebar';
$principalRoleLabelKey = 'admin.account.administrator';
$vendorStyles = $adminAssets->styles('vendor');
$adminStyles = $adminAssets->styles('admin');
$adminScript = $adminAssets->script('admin');
$adminMarkUrl = $adminAssets->staticUrl('images/branding/admin-mark.svg');
?>
<!doctype html>
<html class="no-js" lang="<?= e($locale) ?>" data-bs-theme="light" data-lemonade-sidebar-storage-key="<?= e($sidebarStorageKey) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($helpers->csrfToken()) ?>">
    <title><?= e($this->section('title', $title)) ?> | <?= e($branding->applicationName) ?></title>
    <link rel="icon" href="<?= e($adminMarkUrl) ?>">
    <?php if ($branding->themeColor !== null && $branding->themeColor !== ''): ?><meta name="theme-color" content="<?= e($branding->themeColor) ?>">
    <?php endif; ?>
    <script src="<?= e($adminAssets->staticUrl('js/core/lemonade-theme-init.js')) ?>"></script>
    <script src="<?= e($adminAssets->staticUrl('js/core/lemonade-sidebar-init.js')) ?>"></script>
    <?php foreach (array_merge($vendorStyles, $adminStyles) as $style): ?><link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
</head>
<body class="admin-shell">
    <?php $authorizationMessage = $requestHelpers->flash(AdminAuthorizationResponseHandler::FLASH_KEY); ?>
    <?php if (is_array($authorizationMessage) && isset($authorizationMessage['type'], $authorizationMessage['key'])): ?>
        <?php $authorizationParams = is_array($authorizationMessage['params'] ?? null) ? $authorizationMessage['params'] : []; ?>
        <div hidden data-lemonade-message data-lemonade-message-type="<?= e((string) $authorizationMessage['type']) ?>" data-lemonade-message-key="<?= e((string) $authorizationMessage['key']) ?>" data-lemonade-message-text="<?= e($helpers->lang((string) $authorizationMessage['key'], $authorizationParams)) ?>"<?= $authorizationParams === [] ? '' : ' data-lemonade-message-params="' . e(json_encode($authorizationParams, JSON_THROW_ON_ERROR)) . '"' ?>></div>
        <noscript><div class="alert alert-warning" role="alert"><?= e($helpers->lang((string) $authorizationMessage['key'], $authorizationParams)) ?></div></noscript>
    <?php endif; ?>
    <div class="admin-shell" data-lemonade-admin-shell data-lemonade-admin-base-path="<?= e($helpers->url('admin.dashboard')) ?>" data-lemonade-locale="<?= e($locale) ?>" data-lemonade-i18n-resource-map-source="<?= e($helpers->url('admin.resources.i18n.index')) ?>" data-lemonade-i18n-namespace="admin" data-lemonade-i18n-namespace-source="<?= e($helpers->url('admin.resources.i18n', ['group' => 'admin'])) ?>?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="<?= e(json_encode($clientTranslationVersion->versions('admin'), JSON_THROW_ON_ERROR)) ?>" data-lemonade-navigation-source="<?= e($helpers->url('admin.resources.i18n', ['group' => 'navigation'])) ?>?locale={locale}">
        <aside class="sidebar" aria-label="<?= e($helpers->lang('admin.sidebar.mainNavigation')) ?>" data-lemonade-i18n-aria-label="admin.sidebar.mainNavigation" data-lemonade-sidebar data-lemonade-storage-key="<?= e($sidebarStorageKey) ?>">
            <a class="brand" href="<?= e($helpers->url('admin.dashboard')) ?>"><img class="brand-mark" src="<?= e($adminMarkUrl) ?>" alt="" aria-hidden="true"><span class="brand-copy"><span class="brand-title"><?= e($branding->applicationName) ?></span><span class="brand-subtitle" data-lemonade-i18n="admin.brand.subtitle"><?= e($helpers->lang('admin.brand.subtitle')) ?></span></span></a>
            <?= $this->partial('admin::navigation.sidebar', ['navigation' => $navigation]) ?>
            <div class="sidebar-footer"><button class="collapse-link" type="button" data-lemonade-sidebar-toggle aria-expanded="true" aria-label="<?= e($helpers->lang('admin.sidebar.collapseNavigation')) ?>" data-lemonade-i18n-aria-label="admin.sidebar.collapseNavigation"><i class="<?= e(AdminIcon::ChevronBarLeft->cssClass()) ?>" data-lemonade-sidebar-toggle-icon aria-hidden="true"></i><span data-lemonade-i18n="admin.sidebar.collapse"><?= e($helpers->lang('admin.sidebar.collapse')) ?></span></button></div>
        </aside>
        <div class="admin-frame">
            <header class="topbar">
                <form class="global-search lm-search" role="search">
                    <i class="<?= e(AdminIcon::Search->cssClass()) ?> lm-search-icon" aria-hidden="true"></i>
                    <input class="form-control" type="search" aria-label="<?= e($helpers->lang('admin.topbar.search')) ?>" placeholder="<?= e($helpers->lang('admin.topbar.search')) ?>" data-lemonade-i18n-placeholder="admin.topbar.search" data-lemonade-i18n-aria-label="admin.topbar.search">
                    <kbd>⌘ K</kbd>
                </form>
                <div class="topbar-actions">
                    <button class="icon-button" type="button" aria-label="<?= e($helpers->lang('admin.topbar.help')) ?>" data-lemonade-i18n-aria-label="admin.topbar.help"><i class="<?= e(AdminIcon::QuestionCircle->cssClass()) ?>" aria-hidden="true"></i></button>
                    <?php if ($branding->homeUrl !== null): ?><a class="icon-button" href="<?= e($branding->homeUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($helpers->lang('admin.topbar.viewWebsite')) ?>" title="<?= e($helpers->lang('admin.topbar.viewWebsite')) ?>" data-lemonade-i18n-aria-label="admin.topbar.viewWebsite" data-lemonade-i18n-title="admin.topbar.viewWebsite"><i class="<?= e(AdminIcon::BoxArrowUpRight->cssClass()) ?>" aria-hidden="true"></i></a><?php endif; ?>
                    <div class="lm-theme-control" data-lemonade-dropdown>
                        <button class="icon-button lm-theme-toggle" type="button" data-lemonade-dropdown-trigger aria-expanded="false" aria-label="<?= e($helpers->lang('admin.theme.appearance')) ?>" title="<?= e($helpers->lang('admin.theme.appearance')) ?>" data-lemonade-i18n-aria-label="admin.theme.appearance" data-lemonade-i18n-title="admin.theme.appearance">
                            <i class="<?= e(AdminIcon::MoonStars->cssClass()) ?>" data-lemonade-theme-icon="moon" aria-hidden="true"></i>
                            <i class="<?= e(AdminIcon::Sun->cssClass()) ?>" data-lemonade-theme-icon="sun" aria-hidden="true"></i>
                        </button>
                        <div class="lm-dropdown-menu lm-theme-menu" data-lemonade-dropdown-panel hidden role="menu" aria-label="<?= e($helpers->lang('admin.theme.appearance')) ?>" data-lemonade-i18n-aria-label="admin.theme.appearance">
                            <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="system">
                                <i class="<?= e(AdminIcon::Display->cssClass()) ?>" aria-hidden="true"></i>
                                <span data-lemonade-i18n="admin.theme.system"><?= e($helpers->lang('admin.theme.system')) ?></span>
                                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                            </button>
                            <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="light">
                                <i class="<?= e(AdminIcon::Sun->cssClass()) ?>" aria-hidden="true"></i>
                                <span data-lemonade-i18n="admin.theme.light"><?= e($helpers->lang('admin.theme.light')) ?></span>
                                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                            </button>
                            <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="dark">
                                <i class="<?= e(AdminIcon::MoonStars->cssClass()) ?>" aria-hidden="true"></i>
                                <span data-lemonade-i18n="admin.theme.dark"><?= e($helpers->lang('admin.theme.dark')) ?></span>
                                <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                            </button>
                        </div>
                    </div>
                    <?php if ($currentUser !== null): ?><div class="lm-notifications" data-lemonade-dropdown data-lemonade-notifications data-lemonade-source="<?= e($helpers->url('admin.api.notifications')) ?>?page=1&amp;perPage=8" data-lemonade-indicator-source="<?= e($helpers->url('admin.api.notifications.indicator')) ?>" data-lemonade-action-source="<?= e($helpers->url('admin.notifications.ajax')) ?>">
                        <button class="icon-button" type="button" data-lemonade-dropdown-trigger aria-expanded="false" aria-label="<?= e($helpers->lang('admin.notifications.title')) ?>" data-lemonade-i18n-aria-label="admin.notifications.title"><i class="<?= e(AdminIcon::Bell->cssClass()) ?>" aria-hidden="true"></i></button>
                        <div class="lm-dropdown-menu lm-notifications-menu" data-lemonade-dropdown-panel hidden>
                            <div class="lm-notifications-header"><strong data-lemonade-i18n="admin.notifications.title"><?= e($helpers->lang('admin.notifications.title')) ?></strong><span data-lemonade-notifications-unread hidden></span></div>
                            <div class="lm-notifications-list"><ul class="lm-activity-list lm-activity-list--compact" data-lemonade-notifications-list><li class="lm-notifications-state" data-lemonade-i18n="admin.notifications.loading"><?= e($helpers->lang('admin.notifications.loading')) ?></li></ul></div>
                            <div class="lm-notifications-footer" data-lemonade-notifications-footer hidden><button class="icon-button" type="button" data-lemonade-notifications-mark-read aria-label="<?= e($helpers->lang('admin.notifications.markAllRead')) ?>" title="<?= e($helpers->lang('admin.notifications.markAllRead')) ?>" data-lemonade-i18n-aria-label="admin.notifications.markAllRead" data-lemonade-i18n-title="admin.notifications.markAllRead"><i class="<?= e(AdminIcon::Check2All->cssClass()) ?>" aria-hidden="true"></i></button><a class="icon-button" href="<?= e($helpers->url('admin.notifications')) ?>" data-lemonade-notifications-view-all aria-label="<?= e($helpers->lang('admin.notifications.viewAll')) ?>" title="<?= e($helpers->lang('admin.notifications.viewAll')) ?>" data-lemonade-i18n-aria-label="admin.notifications.viewAll" data-lemonade-i18n-title="admin.notifications.viewAll"><i class="<?= e(AdminIcon::Inbox->cssClass()) ?>" aria-hidden="true"></i></a></div>
                        </div>
                    </div><?php endif; ?>
                    <div class="lm-user-menu-dropdown" data-lemonade-dropdown>
                        <button class="user-menu" type="button" data-lemonade-dropdown-trigger aria-expanded="false" aria-label="<?= e($helpers->lang('admin.topbar.account')) ?>" data-lemonade-i18n-aria-label="admin.topbar.account"><?= $this->partial('admin::components.thumbnail', ['thumbnail' => $avatar]) ?></button>
                        <div class="lm-dropdown-menu lm-user-menu-dropdown-menu" data-lemonade-dropdown-panel hidden>
                            <div class="lm-user-menu-header"><strong><?= e($currentUser?->email() ?? '') ?></strong><?php if ($currentUser !== null): ?><span><?= e($currentUser->email()) ?></span><?php endif; ?><span data-lemonade-i18n="<?= e($principalRoleLabelKey) ?>"><?= e($helpers->lang($principalRoleLabelKey)) ?></span></div>
                            <?php if ($currentUser !== null): ?>
                                <a class="lm-dropdown-item" href="<?= e($helpers->url('admin.system.module.edit', ['module' => 'users', 'id' => $currentUser->id()])) ?>"><i class="<?= e(AdminIcon::Person->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.account.profile"><?= e($helpers->lang('admin.account.profile')) ?></span></a>
                                <div class="lm-dropdown-divider"></div>
                            <?php endif; ?>
                            <form method="post" action="<?= e($helpers->url('admin.logout')) ?>"><?= $helpers->csrfField() ?><button class="lm-dropdown-item lm-user-menu-logout" type="submit"><i class="<?= e(AdminIcon::BoxArrowRight->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.account.logout"><?= e($helpers->lang('admin.account.logout')) ?></span></button></form>
                            <div class="lm-dropdown-divider"></div>
                            <div class="lm-user-menu-language" aria-label="<?= e($helpers->lang('admin.common.language')) ?>" data-lemonade-i18n-aria-label="admin.common.language">
                                <span class="lm-user-menu-language-label" data-lemonade-i18n="admin.common.language"><?= e($helpers->lang('admin.common.language')) ?></span>
                                <button class="lm-dropdown-item lm-user-menu-language-option" type="button" data-lemonade-locale-option="cs" data-lemonade-intl-locale="cs-CZ"><span class="lm-locale-flag lm-locale-flag--cs" aria-hidden="true"></span><span class="lm-locale-label" data-lemonade-i18n="admin.account.czech"><?= e($helpers->lang('admin.account.czech')) ?></span><span class="lm-locale-check" data-lemonade-locale-active hidden><i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" aria-hidden="true"></i></span></button>
                                <button class="lm-dropdown-item lm-user-menu-language-option" type="button" data-lemonade-locale-option="en" data-lemonade-intl-locale="en-GB"><span class="lm-locale-flag lm-locale-flag--en" aria-hidden="true"></span><span class="lm-locale-label" data-lemonade-i18n="admin.account.english"><?= e($helpers->lang('admin.account.english')) ?></span><span class="lm-locale-check" data-lemonade-locale-active hidden><i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" aria-hidden="true"></i></span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <main class="content-area"><div data-lemonade-page-root tabindex="-1"><?= $content ?></div></main>
        </div>
    </div>
    <?php if ($adminScript !== ''): ?><script type="module" src="<?= e($adminScript) ?>"></script><?php endif; ?>
</body>
</html>
