<?php

declare(strict_types=1);

use Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderer;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminFileUploadCollection;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var ClientTranslationVersion $clientTranslationVersion
 * @var View $this
 * @var array<string, mixed> $user
 * @var string $section
 * @var list<array<string, mixed>> $activity
 * @var string|null $profileUpdatedAt
 * @var string|null $passwordChangedAt
 * @var string|null $mfaEnabledAt
 * @var array<string, string> $passwordErrors
 * @var bool $passwordChanged
 * @var AdminFileUploadCollection $avatarUpload
 * @var AdminEditorDefinition|null $adminEditor
 * @var AdminEditorRenderContext|null $adminEditorContext
 * @var AdminEditorFieldRenderer $adminEditorFields
 */

$name = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
$name = $name === '' ? (string) $user['email'] : $name;
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$sections = [
    'basic' => ['route' => 'admin.system.users.profile', 'label' => 'users.profile.navigation.basic', 'icon' => AdminIcon::Person],
    'security' => ['route' => 'admin.system.users.profile.security', 'label' => 'users.profile.navigation.security', 'icon' => AdminIcon::Shield],
    'preferences' => ['route' => 'admin.system.users.profile.preferences', 'label' => 'users.profile.navigation.preferences', 'icon' => AdminIcon::Sliders],
    'activity' => ['route' => 'admin.system.users.profile.activity', 'label' => 'users.profile.navigation.activity', 'icon' => AdminIcon::ClockHistory],
];
$dateTime = static fn(?string $value): ?string => $value === null || $value === '' ? null : str_replace(' ', 'T', $value);
$unknown = 'users.profile.value_unknown';
?>
<div class="lm-user-profile" data-lemonade-i18n-namespace="users" data-lemonade-i18n-namespace-source="<?= e($helpers->url('admin.resources.i18n', ['group' => 'users'])) ?>?locale={locale}&amp;v={version}" data-lemonade-i18n-namespace-versions="<?= e(json_encode($clientTranslationVersion->versions('users'), JSON_THROW_ON_ERROR)) ?>">
    <header class="lm-user-profile-heading">
        <div>
            <span data-lemonade-i18n="users.profile.eyebrow"><?= e($helpers->lang('users.profile.eyebrow')) ?></span>
            <h1 data-lemonade-i18n="users.profile.title"><?= e($helpers->lang('users.profile.title')) ?></h1>
            <p data-lemonade-i18n="users.profile.description"><?= e($helpers->lang('users.profile.description')) ?></p>
        </div>
        <?php if ($section === 'basic'): ?>
            <button class="btn btn-primary lm-button-with-icon" type="submit" form="users-profile-form">
                <i class="<?= e(AdminIcon::Floppy->cssClass()) ?>" aria-hidden="true"></i>
                <span data-lemonade-i18n="admin.common.save_changes"><?= e($helpers->lang('admin.common.save_changes')) ?></span>
            </button>
        <?php endif; ?>
    </header>

    <div class="lm-user-profile-layout">
        <aside class="lm-user-profile-aside">
            <div class="lm-user-profile-card">
                <div class="lm-user-profile-avatar">
                    <?= $this->partial('admin::components.thumbnail', ['thumbnail' => $avatarUpload->thumbnail()]) ?>
                    <?= $this->partial('admin::components.file-upload-avatar', ['uploadCollection' => $avatarUpload]) ?>
                </div>
                <strong><?= e($name) ?></strong>
                <?php if ($roles === []): ?>
                    <span class="lm-user-profile-role" data-lemonade-i18n="users.list.no_role"><?= e($helpers->lang('users.list.no_role')) ?></span>
                <?php else: ?>
                    <span class="lm-user-profile-role"><?= e(implode(', ', array_map(static fn(array $role): string => (string) $role['name'], $roles))) ?></span>
                <?php endif; ?>
                <span class="lm-user-profile-status<?= (int) $user['active'] === 1 ? ' is-active' : '' ?>" data-lemonade-i18n="<?= (int) $user['active'] === 1 ? 'users.status.active' : 'users.status.inactive' ?>"><?= e($helpers->lang((int) $user['active'] === 1 ? 'users.status.active' : 'users.status.inactive')) ?></span>
            </div>
            <nav class="lm-user-profile-navigation" aria-label="<?= e($helpers->lang('users.profile.navigation.label')) ?>" data-lemonade-i18n-aria-label="users.profile.navigation.label">
                <?php foreach ($sections as $code => $item): ?>
                    <a href="<?= e($helpers->url($item['route'])) ?>"<?= $section === $code ? ' class="is-active" aria-current="page"' : '' ?>>
                        <i class="<?= e($item['icon']->cssClass()) ?>" aria-hidden="true"></i>
                        <span data-lemonade-i18n="<?= e($item['label']) ?>"><?= e($helpers->lang($item['label'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="lm-user-profile-content">
            <?php if ($section === 'basic' && $adminEditor !== null && $adminEditorContext !== null): ?>
                <section class="lm-user-profile-panel">
                    <div class="lm-user-profile-section-heading">
                        <i class="<?= e(AdminIcon::Person->cssClass()) ?>" aria-hidden="true"></i>
                        <div>
                            <h2 data-lemonade-i18n="users.profile.basic.title"><?= e($helpers->lang('users.profile.basic.title')) ?></h2>
                            <p data-lemonade-i18n="users.profile.basic.description"><?= e($helpers->lang('users.profile.basic.description')) ?></p>
                        </div>
                    </div>
                    <?= (new AdminEditorRenderer($this, $helpers, $adminEditorFields))->render($adminEditor, $adminEditorContext) ?>
                </section>

                <section class="lm-user-profile-panel">
                    <div class="lm-user-profile-section-heading">
                        <i class="<?= e(AdminIcon::InfoCircle->cssClass()) ?>" aria-hidden="true"></i>
                        <div>
                            <h2 data-lemonade-i18n="users.profile.account.title"><?= e($helpers->lang('users.profile.account.title')) ?></h2>
                            <p data-lemonade-i18n="users.profile.account.description"><?= e($helpers->lang('users.profile.account.description')) ?></p>
                        </div>
                    </div>
                    <dl class="lm-user-profile-metadata">
                        <div>
                            <dt data-lemonade-i18n="users.profile.account.id"><?= e($helpers->lang('users.profile.account.id')) ?></dt>
                            <dd><?= e((string) $user['id']) ?></dd>
                        </div>
                        <div>
                            <dt data-lemonade-i18n="users.profile.account.roles"><?= e($helpers->lang('users.profile.account.roles')) ?></dt>
                            <dd><?= $roles === [] ? e($helpers->lang('users.list.no_role')) : e(implode(', ', array_map(static fn(array $role): string => (string) $role['name'], $roles))) ?></dd>
                        </div>
                        <div>
                            <dt data-lemonade-i18n="users.profile.account.created_at"><?= e($helpers->lang('users.profile.account.created_at')) ?></dt>
                            <dd><time datetime="<?= e((string) $dateTime((string) $user['created_at'])) ?>" data-lemonade-i18n-datetime><?= e((string) $user['created_at']) ?></time></dd>
                        </div>
                        <div>
                            <dt data-lemonade-i18n="users.profile.account.last_login"><?= e($helpers->lang('users.profile.account.last_login')) ?></dt>
                            <dd>
                                <?php if (($lastLogin = $dateTime(is_string($user['last_login_at'] ?? null) ? $user['last_login_at'] : null)) === null): ?>
                                    <span data-lemonade-i18n="users.list.never_logged_in"><?= e($helpers->lang('users.list.never_logged_in')) ?></span>
                                <?php else: ?>
                                    <time datetime="<?= e($lastLogin) ?>" data-lemonade-i18n-datetime><?= e((string) $user['last_login_at']) ?></time>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt data-lemonade-i18n="users.profile.account.profile_updated_at"><?= e($helpers->lang('users.profile.account.profile_updated_at')) ?></dt>
                            <dd>
                                <?php if (($updated = $dateTime($profileUpdatedAt)) === null): ?>
                                    <span data-lemonade-i18n="<?= e($unknown) ?>"><?= e($helpers->lang($unknown)) ?></span>
                                <?php else: ?>
                                    <time datetime="<?= e($updated) ?>" data-lemonade-i18n-datetime><?= e((string) $profileUpdatedAt) ?></time>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </section>
            <?php elseif ($section === 'security'): ?>
                <?= $this->partial('users::profile.security', compact('user', 'passwordErrors', 'passwordChanged', 'passwordChangedAt', 'mfaEnabledAt')) ?>
            <?php elseif ($section === 'preferences'): ?>
                <?= $this->partial('users::profile.preferences') ?>
            <?php else: ?>
                <?= $this->partial('users::profile.activity', ['activity' => $activity, 'currentUserId' => (int) $user['id']]) ?>
            <?php endif; ?>
        </main>
    </div>
</div>
