<?php

declare(strict_types=1);

use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var string $locale */
/** @var string $title */
/** @var bool $installer */
/** @var View $this */
/** @var ViewHelpers $helpers */
/** @var ClientTranslationVersion $clientTranslationVersion */
/** @var AdminBranding $branding */
/** @var AdminAssetManifest $adminAssets */
/** @var string $content */

$isInstaller = $installer ?? false;
$translationNamespace = $isInstaller ? 'install' : 'auth';
$brandSubtitleKey = $translationNamespace . '.brand.subtitle';
$poweredByKey = $translationNamespace . '.brand.poweredByLemonade';
$themeAppearanceKey = $translationNamespace . '.theme.appearance';
$themeSystemKey = $translationNamespace . '.theme.system';
$themeLightKey = $translationNamespace . '.theme.light';
$themeDarkKey = $translationNamespace . '.theme.dark';
$localeLabelKey = $translationNamespace . '.locale.label';
$localeCzechKey = $translationNamespace . '.locale.czech';
$localeEnglishKey = $translationNamespace . '.locale.english';
$vendorStyles = $adminAssets->styles('vendor');
$authStyles = $adminAssets->styles('auth');
$authScript = $isInstaller ? '' : $adminAssets->script('auth');
$installerStyles = $isInstaller ? $adminAssets->styles('installer') : [];
$installerScript = $isInstaller ? $adminAssets->script('installer') : '';
$adminMarkUrl = $adminAssets->staticUrl('images/branding/admin-mark.svg');
$currentDateTime = date(DATE_ATOM);
?>
<!doctype html>
<html class="no-js" lang="<?= e($locale) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($helpers->csrfToken()) ?>">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e($adminMarkUrl) ?>">
    <?php if ($branding->themeColor !== null && $branding->themeColor !== ''): ?><meta name="theme-color" content="<?= e($branding->themeColor) ?>">
    <?php endif; ?>
    <script src="<?= e($adminAssets->staticUrl('js/core/lemonade-theme-init.js')) ?>"></script>
    <?php foreach (array_merge($vendorStyles, $authStyles, $installerStyles) as $style): ?><link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
</head>
<body class="lemonade-auth">
    <main class="lemonade-auth"<?= $isInstaller ? ' data-lemonade-install-shell data-lemonade-locale="' . e($locale) . '"' : ' data-lemonade-admin-shell data-lemonade-locale="' . e($locale) . '" data-lemonade-i18n-resource-map-source="' . e($helpers->url('admin.resources.i18n.index')) . '" data-lemonade-i18n-namespace="auth" data-lemonade-i18n-namespace-source="' . e($helpers->url('admin.resources.i18n', ['group' => 'auth'])) . '?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="' . e(json_encode($clientTranslationVersion->versions('auth'), JSON_THROW_ON_ERROR)) . '"' ?>>
        <section class="lemonade-auth-layout<?= $isInstaller ? ' lemonade-auth-layout--installer' : '' ?>" aria-labelledby="auth-title">
            <div class="lemonade-auth-panel">
                <?php if ($branding->homeUrl !== null): ?><a class="lemonade-auth-brand" href="<?= e($branding->homeUrl) ?>" aria-label="<?= e($branding->applicationName) ?>"><?php else: ?><div class="lemonade-auth-brand"><?php endif; ?><img class="lemonade-auth-brand-mark" src="<?= e($adminMarkUrl) ?>" alt="" aria-hidden="true"><span class="lemonade-auth-brand-copy"><span class="lemonade-auth-brand-title"><?= e($branding->applicationName) ?></span><span class="lemonade-auth-brand-subtitle" data-lemonade-i18n="<?= e($brandSubtitleKey) ?>"><?= e($helpers->lang($brandSubtitleKey)) ?></span></span><?php if ($branding->homeUrl !== null): ?></a><?php else: ?></div><?php endif; ?>
                <div class="lemonade-auth-content">
                    <?= $content ?>
                </div>
                <footer class="lemonade-auth-footer">
                    <div class="lemonade-auth-footer-copy">
                        <span><?= e($branding->applicationName) ?> · v1.0</span>
                        <a class="lemonade-auth-framework" href="https://lemonadeframework.cz/" target="_blank" rel="noopener noreferrer">
                            <img src="<?= e($adminAssets->staticUrl('images/branding/lemonade-framework.svg')) ?>" alt="" aria-hidden="true">
                                <span data-lemonade-i18n="<?= e($poweredByKey) ?>">
                                <?= e($helpers->lang($poweredByKey)) ?>
                            </span>
                        </a>
                    </div>
                    <div class="lemonade-auth-footer-controls">
                        <div class="lm-theme-control" data-lemonade-dropdown data-lemonade-dropdown-placement="top-end">
                            <button class="lm-theme-toggle" type="button" data-lemonade-dropdown-trigger aria-expanded="false" aria-label="<?= e($helpers->lang($themeAppearanceKey)) ?>" title="<?= e($helpers->lang($themeAppearanceKey)) ?>" data-lemonade-i18n-aria-label="<?= e($themeAppearanceKey) ?>" data-lemonade-i18n-title="<?= e($themeAppearanceKey) ?>">
                                <i class="<?= e(AdminIcon::MoonStars->cssClass()) ?>" data-lemonade-theme-icon="moon" aria-hidden="true"></i>
                                <i class="<?= e(AdminIcon::Sun->cssClass()) ?>" data-lemonade-theme-icon="sun" aria-hidden="true"></i>
                            </button>
                            <div class="lm-dropdown-menu lm-theme-menu" data-lemonade-dropdown-panel hidden role="menu" aria-label="<?= e($helpers->lang($themeAppearanceKey)) ?>" data-lemonade-i18n-aria-label="<?= e($themeAppearanceKey) ?>">
                                <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="system">
                                    <i class="<?= e(AdminIcon::Display->cssClass()) ?>" aria-hidden="true"></i>
                                    <span data-lemonade-i18n="<?= e($themeSystemKey) ?>"><?= e($helpers->lang($themeSystemKey)) ?></span>
                                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                                </button>
                                <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="light">
                                    <i class="<?= e(AdminIcon::Sun->cssClass()) ?>" aria-hidden="true"></i>
                                    <span data-lemonade-i18n="<?= e($themeLightKey) ?>"><?= e($helpers->lang($themeLightKey)) ?></span>
                                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                                </button>
                                <button class="lm-dropdown-item lm-theme-option" type="button" role="menuitemradio" aria-checked="false" data-lemonade-theme-option="dark">
                                    <i class="<?= e(AdminIcon::MoonStars->cssClass()) ?>" aria-hidden="true"></i>
                                    <span data-lemonade-i18n="<?= e($themeDarkKey) ?>"><?= e($helpers->lang($themeDarkKey)) ?></span>
                                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" data-lemonade-theme-check aria-hidden="true" hidden></i>
                                </button>
                            </div>
                        </div>
                        <div class="lemonade-auth-language" aria-label="<?= e($helpers->lang($localeLabelKey)) ?>" data-lemonade-i18n-aria-label="<?= e($localeLabelKey) ?>">
                            <button type="button" data-lemonade-locale-option="cs" data-lemonade-intl-locale="cs-CZ">
                                <span class="lm-locale-flag lm-locale-flag--cs" aria-hidden="true"></span>
                                <span class="lm-locale-label" data-lemonade-i18n="<?= e($localeCzechKey) ?>"><?= e($helpers->lang($localeCzechKey)) ?></span>
                                <span class="lm-locale-check" data-lemonade-locale-active hidden>
                                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" aria-hidden="true"></i>
                                </span>
                            </button>
                            <button type="button" data-lemonade-locale-option="en" data-lemonade-intl-locale="en-GB">
                                <span class="lm-locale-flag lm-locale-flag--en" aria-hidden="true"></span>
                                <span class="lm-locale-label" data-lemonade-i18n="<?= e($localeEnglishKey) ?>"><?= e($helpers->lang($localeEnglishKey)) ?></span>
                                <span class="lm-locale-check" data-lemonade-locale-active hidden>
                                    <i class="<?= e(AdminIcon::CheckLg->cssClass()) ?>" aria-hidden="true"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </footer>
            </div>
            <?php if (!$isInstaller): ?>
                <aside class="lemonade-auth-visual" aria-hidden="true">
                    <div class="lemonade-auth-visual-time">
                        <time class="lemonade-auth-visual-clock" datetime="<?= e($currentDateTime) ?>" data-lemonade-clock data-lemonade-clock-format="time" data-lemonade-time-zone="Europe/Prague"><?= e(date('H:i:s')) ?></time>
                        <time class="lemonade-auth-visual-date" datetime="<?= e($currentDateTime) ?>" data-lemonade-clock data-lemonade-clock-format="date" data-lemonade-time-zone="Europe/Prague"><?= e(date('j. n. Y')) ?></time>
                    </div>
                </aside>
            <?php endif; ?>
        </section>
        <?php if ($branding->hasPartner()): ?><div class="lm-auth-partner"><a href="<?= e($branding->partnerUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($branding->partnerLabel) ?>"><img src="<?= e($branding->partnerLogoUrl) ?>" alt="<?= e($branding->partnerLabel) ?>"></a></div><?php endif; ?>
    </main>
    <?php if ($installerScript !== ''): ?><script type="module" src="<?= e($installerScript) ?>"></script><?php endif; ?>
    <?php if ($authScript !== ''): ?><script type="module" src="<?= e($authScript) ?>"></script><?php endif; ?>
</body>
</html>
