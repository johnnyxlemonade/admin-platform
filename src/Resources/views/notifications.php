<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/** @var View $this */
/** @var ViewHelpers $helpers */
/** @var array{items:list<array{id:int,type:string,title:string,message:string,author:string,icon:string,createdAt:string,read:bool}>,unread:int,pagination:array{page:int,perPage:int,hasMore:bool}} $notifications */
/** @var int $inboxPerPage */
?>
<?php $this->start('title'); ?><?= e($helpers->lang('admin.notifications.title')) ?><?php $this->end(); ?>
<div data-lemonade-notifications data-lemonade-notifications-inbox data-lemonade-notifications-per-page="<?= e((string) $inboxPerPage) ?>" data-lemonade-source="<?= e($helpers->url('admin.api.notifications')) ?>?page=1&amp;perPage=<?= e((string) $inboxPerPage) ?>" data-lemonade-action-source="<?= e($helpers->url('admin.notifications.ajax')) ?>">
    <header class="page-heading">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e($helpers->url('admin.dashboard')) ?>" data-lemonade-i18n="admin.navigation.dashboard"><?= e($helpers->lang('admin.navigation.dashboard')) ?></a></li><li class="breadcrumb-item active" aria-current="page" data-lemonade-i18n="admin.notifications.title"><?= e($helpers->lang('admin.notifications.title')) ?></li></ol></nav>
            <h1 data-lemonade-i18n="admin.notifications.title"><?= e($helpers->lang('admin.notifications.title')) ?></h1>
            <p data-lemonade-i18n="admin.notifications.description"><?= e($helpers->lang('admin.notifications.description')) ?></p>
        </div>
        <?php if ($notifications['unread'] > 0): ?>
            <button class="btn btn-primary lm-button-primary lm-button-with-icon" type="button" data-lemonade-notifications-mark-read aria-label="<?= e($helpers->lang('admin.notifications.markAllRead')) ?>" data-lemonade-i18n-aria-label="admin.notifications.markAllRead"><i class="<?= e(AdminIcon::Check2All->cssClass()) ?>" aria-hidden="true"></i><span data-lemonade-i18n="admin.notifications.markAllRead"><?= e($helpers->lang('admin.notifications.markAllRead')) ?></span></button>
        <?php endif; ?>
    </header>
    <section class="lm-notifications-page">
    <ul class="lm-activity-list lm-activity-list--full lm-notifications-list" data-lemonade-notifications-list>
            <?php if ($notifications['items'] === []): ?>
                <li class="lm-notifications-empty"><i class="<?= e(AdminIcon::Inbox->cssClass()) ?>" aria-hidden="true"></i><p data-lemonade-i18n="admin.notifications.empty"><?= e($helpers->lang('admin.notifications.empty')) ?></p></li>
            <?php else: ?>
                <?php foreach ($notifications['items'] as $notification): ?>
                    <?php $bodyId = 'notification-body-' . $notification['id']; ?>
                    <li class="lm-accordion lm-notification-card lm-notification-item<?= $notification['read'] ? '' : ' lm-activity-item--unread' ?>" data-lemonade-accordion data-lemonade-notification-id="<?= e((string) $notification['id']) ?>"><button class="lm-accordion-trigger lm-notification-toggle" type="button" data-lemonade-accordion-trigger aria-expanded="false" aria-controls="<?= e($bodyId) ?>"><span class="lm-activity-icon"><i class="<?= e((AdminIcon::tryFrom($notification['icon']) ?? AdminIcon::Activity)->cssClass()) ?>" aria-hidden="true"></i></span><span class="lm-notification-content"><strong class="lm-activity-author"><?= e($notification['author']) ?></strong><span class="lm-notification-title"><?= e($notification['title']) ?></span></span><span class="lm-notification-trailing"><time class="lm-activity-time" datetime="<?= e($notification['createdAt']) ?>" data-lemonade-i18n-date><?= e($notification['createdAt']) ?></time><?php if (!$notification['read']): ?><span class="lm-activity-unread" aria-hidden="true"></span><?php endif; ?><i class="<?= e(AdminIcon::ChevronDown->cssClass()) ?> lm-accordion-chevron" aria-hidden="true"></i></span></button><div class="lm-accordion-panel lm-notification-body" id="<?= e($bodyId) ?>" data-lemonade-accordion-panel hidden><p><?= e($notification['message']) ?></p></div></li>
                <?php endforeach; ?>
            <?php endif; ?>
    </ul>
    <div class="lm-notifications-load-more-skeleton" data-lemonade-notifications-load-more-skeleton aria-busy="false" hidden></div>
    <?php if ($notifications['pagination']['hasMore']): ?>
        <div class="lm-notifications-load-more"><button class="btn btn-light btn-sm" type="button" data-lemonade-notifications-load-more data-lemonade-i18n="admin.notifications.loadMore"><?= e($helpers->lang('admin.notifications.loadMore')) ?></button></div>
    <?php endif; ?>
    </section>
</div>
