<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var View $this */
/** @var ViewHelpers $helpers */
/** @var list<array{code:string,translationGroup:string,titleKey:string,title:string,icon:string|null,size:string,supportedSizes:list<string>,membership:string,managed:bool,protection:string|null,removable:bool,skeleton:string,position:int,contentEndpoint:string}> $dashboardWidgets */
/** @var list<array{namespace:string,source:string,versions:array<string,string>}> $widgetTranslationResources */

$now = date(DATE_ATOM);
?>
<?php $this->start('title'); ?><?= e($helpers->lang('admin.dashboard.title')) ?><?php $this->end(); ?>
<section
    class="lm-dashboard"
    data-lemonade-dashboard-widgets
    data-lemonade-dashboard-source="<?= e($helpers->url('admin.api.dashboard')) ?>"
    data-lemonade-dashboard-mutation-source="<?= e($helpers->url('admin.dashboard.widgets.ajax')) ?>"
    data-lemonade-dashboard-loading="<?= e($helpers->lang('admin.dashboard.widgets.loading')) ?>"
    data-lemonade-dashboard-error="<?= e($helpers->lang('admin.dashboard.widgets.error')) ?>"
    data-lemonade-dashboard-retry="<?= e($helpers->lang('admin.dashboard.widgets.retry')) ?>"
>
    <?php foreach ($widgetTranslationResources as $resource): ?>
        <span hidden data-lemonade-i18n-namespace="<?= e($resource['namespace']) ?>" data-lemonade-i18n-namespace-source="<?= e($resource['source']) ?>" data-lemonade-i18n-namespace-versions="<?= e(json_encode($resource['versions'], JSON_THROW_ON_ERROR)) ?>"></span>
    <?php endforeach; ?>
    <header class="lm-dashboard-header">
        <div class="lm-dashboard-header-main">
            <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active" aria-current="page" data-lemonade-i18n="admin.navigation.dashboard"><?= e($helpers->lang('admin.navigation.dashboard')) ?></li></ol></nav>
            <h1 data-lemonade-i18n="admin.dashboard.title"><?= e($helpers->lang('admin.dashboard.title')) ?></h1>
            <p data-lemonade-i18n="admin.dashboard.description"><?= e($helpers->lang('admin.dashboard.description')) ?></p>
        </div>
        <div class="lm-dashboard-now" aria-label="<?= e($helpers->lang('admin.dashboard.currentDateTime')) ?>" data-lemonade-i18n-aria-label="admin.dashboard.currentDateTime">
            <time datetime="<?= e($now) ?>" data-lemonade-i18n-date><?= e(date('j. n. Y')) ?></time>
            <time class="lm-dashboard-time" data-lemonade-clock data-lemonade-clock-format="time" data-lemonade-time-zone="Europe/Prague" datetime="<?= e($now) ?>"><?= e(date('H:i:s')) ?></time>
        </div>
    </header>
    <div class="lm-dashboard-header-actions">
        <button class="btn btn-light lm-button-with-icon" type="button" data-lemonade-dashboard-customize data-lemonade-dashboard-customize-trigger>
            <i class="<?= e(AdminIcon::Sliders->cssClass()) ?>" aria-hidden="true"></i>
            <span data-lemonade-i18n="admin.dashboard.widgets.customize"><?= e($helpers->lang('admin.dashboard.widgets.customize')) ?></span>
        </button>
    </div>
    <div class="lm-dashboard-customize-toolbar" data-lemonade-dashboard-customize-toolbar hidden>
        <button class="btn btn-light lm-button-with-icon" type="button" data-lemonade-dashboard-add-widget><i class="<?= e(AdminIcon::PlusLg->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.dashboard.widgets.add"><?= e($helpers->lang('admin.dashboard.widgets.add')) ?></span></button>
        <button class="btn btn-primary lm-button-primary" type="button" data-lemonade-dashboard-customize-done data-lemonade-i18n="admin.dashboard.widgets.done"><?= e($helpers->lang('admin.dashboard.widgets.done')) ?></button>
    </div>
    <?= $this->partial('admin::dashboard.widget-area', ['dashboardWidgets' => $dashboardWidgets]) ?>
</section>
