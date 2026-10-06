<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/** @var ViewHelpers $helpers */
?>
<div>
    <header class="page-heading">
        <div>
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= e($helpers->url('admin.dashboard')) ?>" data-lemonade-i18n="admin.navigation.dashboard"><?= e($helpers->lang('admin.navigation.dashboard')) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page" data-lemonade-i18n="admin.errors.not_found.breadcrumb"><?= e($helpers->lang('admin.errors.not_found.breadcrumb')) ?></li>
                </ol>
            </nav>
            <h1 id="admin-not-found-title" class="h2 mb-2" data-lemonade-i18n="admin.errors.not_found.title"><?= e($helpers->lang('admin.errors.not_found.title')) ?></h1>
            <p class="text-muted mb-0" data-lemonade-i18n="admin.errors.not_found.message"><?= e($helpers->lang('admin.errors.not_found.message')) ?></p>
        </div>
    </header>
    <section aria-labelledby="admin-not-found-title">
        <div class="card data-card">
            <div class="card-body lm-admin-error-state">
                <span class="lm-admin-error-state-icon">
                    <i class="<?= e(AdminIcon::Search->cssClass()) ?>" aria-hidden="true"></i>
                </span>
                <p class="lm-admin-error-state-code">404</p>
                <h2 class="lm-admin-error-state-title" data-lemonade-i18n="admin.errors.not_found.card_title"><?= e($helpers->lang('admin.errors.not_found.card_title')) ?></h2>
                <p class="lm-admin-error-state-description" data-lemonade-i18n="admin.errors.not_found.card_message"><?= e($helpers->lang('admin.errors.not_found.card_message')) ?></p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a class="btn btn-primary lm-button-primary lm-button-with-icon" href="<?= e($helpers->url('admin.dashboard')) ?>">
                        <i class="<?= e(AdminIcon::HouseDoor->cssClass()) ?>" aria-hidden="true"></i>
                        <span data-lemonade-i18n="admin.errors.not_found.go_to_overview"><?= e($helpers->lang('admin.errors.not_found.go_to_overview')) ?></span>
                    </a>
                    <button class="btn btn-outline-secondary lm-button-with-icon" type="button" onclick="window.history.back()">
                        <i class="<?= e(AdminIcon::ArrowLeft->cssClass()) ?>" aria-hidden="true"></i>
                        <span data-lemonade-i18n="admin.errors.not_found.back"><?= e($helpers->lang('admin.errors.not_found.back')) ?></span>
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>
