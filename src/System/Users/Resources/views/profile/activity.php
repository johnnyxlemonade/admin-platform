<?php

declare(strict_types=1);

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var list<array<string, mixed>> $activity
 * @var int $currentUserId
 */

$presentations = [
    'system.authentication.login' => ['users.profile.activity.events.login', AdminIcon::BoxArrowRight],
    'system.authentication.logout' => ['users.profile.activity.events.logout', AdminIcon::BoxArrowRight],
    'system.users.created' => ['users.profile.activity.events.created', AdminIcon::PersonAdd],
    'system.users.updated' => ['users.profile.activity.events.updated', AdminIcon::PencilSquare],
    'system.users.password_changed' => ['users.profile.activity.events.password_changed', AdminIcon::Key],
    'system.authentication.mfa_enrolled' => ['users.profile.activity.events.mfa_enabled', AdminIcon::ShieldCheck],
    'system.authentication.mfa_disabled' => ['users.profile.activity.events.mfa_disabled', AdminIcon::Shield],
];
?>
<section class="lm-user-profile-panel">
    <div class="lm-user-profile-section-heading">
        <i class="<?= e(AdminIcon::ClockHistory->cssClass()) ?>" aria-hidden="true"></i>
        <div>
            <h2 data-lemonade-i18n="users.profile.activity.title"><?= e($helpers->lang('users.profile.activity.title')) ?></h2>
            <p data-lemonade-i18n="users.profile.activity.description"><?= e($helpers->lang('users.profile.activity.description')) ?></p>
        </div>
    </div>

    <?php if ($activity === []): ?>
        <div class="lm-user-profile-empty">
            <i class="<?= e(AdminIcon::Activity->cssClass()) ?>" aria-hidden="true"></i>
            <strong data-lemonade-i18n="users.profile.activity.empty_title"><?= e($helpers->lang('users.profile.activity.empty_title')) ?></strong>
            <span data-lemonade-i18n="users.profile.activity.empty_description"><?= e($helpers->lang('users.profile.activity.empty_description')) ?></span>
        </div>
    <?php else: ?>
        <ol class="lm-user-profile-activity">
            <?php foreach ($activity as $event): ?>
                <?php
                [$labelKey, $icon] = $presentations[(string) $event['event_code']];
                $actorName = trim((string) ($event['actor_first_name'] ?? '') . ' ' . (string) ($event['actor_last_name'] ?? ''));
                $actorName = $actorName !== '' ? $actorName : (string) ($event['actor_email'] ?? $event['actor_key']);
                $isSelf = (int) ($event['actor_user_id'] ?? 0) === $currentUserId;
                ?>
                <li>
                    <span class="lm-user-profile-activity-icon">
                        <i class="<?= e($icon->cssClass()) ?>" aria-hidden="true"></i>
                    </span>
                    <div>
                        <strong data-lemonade-i18n="<?= e($labelKey) ?>"><?= e($helpers->lang($labelKey)) ?></strong>
                        <?php if (!$isSelf): ?>
                            <span>
                                <span data-lemonade-i18n="users.profile.activity.actor"><?= e($helpers->lang('users.profile.activity.actor')) ?></span>:
                                <?= e($actorName) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <time datetime="<?= e(str_replace(' ', 'T', (string) $event['created_at'])) ?>" data-lemonade-i18n-datetime><?= e((string) $event['created_at']) ?></time>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
