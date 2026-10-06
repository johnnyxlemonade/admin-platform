<?php

declare(strict_types=1);

use Lemonade\Admin\Presentation\AdminFileUploadCollection;
use Lemonade\Admin\Presentation\AdminThumbnail;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;

/**
 * @var ViewHelpers $helpers
 * @var View $this
 * @var array{id:int,first_name:string,last_name:string,active:int,created_at:string,last_login_at:string|null,roles:list<array{id:int,code:string,name:string,is_super_admin:int}>} $user
 * @var AdminThumbnail $thumbnail
 * @var AdminFileUploadCollection|null $avatarUpload
 */

$name = trim($user['first_name'] . ' ' . $user['last_name']);
$createdAt = str_replace(' ', 'T', $user['created_at']);
$lastLoginAt = $user['last_login_at'] === null ? null : str_replace(' ', 'T', $user['last_login_at']);
$statusKey = (int) $user['active'] === 1 ? 'users.status.active' : 'users.status.inactive';
$roleName = $user['roles'][0]['name'] ?? null;
?>
<div class="lm-user-editor-summary">
    <div class="lm-user-editor-summary-avatar">
        <?= $this->partial('admin::components.thumbnail', ['thumbnail' => $thumbnail]) ?>
        <?php if ($avatarUpload !== null): ?><?= $this->partial('admin::components.file-upload-avatar', ['uploadCollection' => $avatarUpload]) ?><?php endif; ?>
    </div>
    <strong class="lm-user-editor-summary-name"><?= e($name) ?></strong>
    <?php if (is_string($roleName) && $roleName !== ''): ?><span class="lm-user-editor-summary-role"><?= e($roleName) ?></span><?php endif; ?>
    <span class="lm-user-editor-summary-status<?= (int) $user['active'] === 1 ? ' is-active' : '' ?>" data-lemonade-i18n="<?= e($statusKey) ?>"><?= e($helpers->lang($statusKey)) ?></span>
    <dl class="lm-user-editor-summary-metadata">
        <div>
            <dt data-lemonade-i18n="users.editor.summary.created_at"><?= e($helpers->lang('users.editor.summary.created_at')) ?></dt>
            <dd><time datetime="<?= e($createdAt) ?>" data-lemonade-i18n-date><?= e($user['created_at']) ?></time></dd>
        </div>
        <div>
            <dt data-lemonade-i18n="users.editor.summary.last_login"><?= e($helpers->lang('users.editor.summary.last_login')) ?></dt>
            <dd><?php if ($lastLoginAt === null): ?><span data-lemonade-i18n="users.list.never_logged_in"><?= e($helpers->lang('users.list.never_logged_in')) ?></span><?php else: ?><time datetime="<?= e($lastLoginAt) ?>" data-lemonade-i18n-date><?= e($user['last_login_at']) ?></time><?php endif; ?></dd>
        </div>
    </dl>
</div>
