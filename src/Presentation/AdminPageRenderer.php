<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Vykresluje administracni stranky se sdilenym layoutem a navigaci
 */
final readonly class AdminPageRenderer
{
    /**
     * Nastavuje renderer, navigaci, aktualniho uzivatele a verzi prekladu
     */
    public function __construct(
        private ViewRendererInterface $views,
        private AdminNavigation $navigation,
        private CurrentUserProvider $currentUser,
        private AuthorizationService $authorization,
        private ClientTranslationVersion $translationVersion,
        private AdminBranding $branding,
        private AdminAssetManifest $assets,
        private AdminFileModel $files,
        private AdminThumbnailComponent $thumbnails,
    ) {}

    /**
     * Vykresluje obsah stranky do administracniho layoutu
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        $currentUser = $this->currentUser->currentUser();
        $avatarInitials = AdminAvatarInitials::resolve($currentUser);
        $avatar = $currentUser === null
            ? $this->thumbnails->fallback($avatarInitials, 'compact', 'circle')
            : $this->avatar($currentUser->id(), $avatarInitials);

        $pageData = [
            'activeAdminModule' => null,
            ...$data,
            'navigation' => $this->navigation->items(),
            'currentUser' => $currentUser,
            'isRootUser' => $currentUser !== null && $this->authorization->isRoot($currentUser),
            'avatar' => $avatar,
            'clientTranslationVersion' => $this->translationVersion,
            'branding' => $this->branding,
            'adminAssets' => $this->assets,
        ];

        $layoutData = [
            ...$pageData,
            'content' => $this->views->content($view, $pageData),
        ];

        return $this->views->render(
            'admin::layouts.admin',
            $layoutData,
            $status,
        );
    }

    /**
     * Vytvari topbar avatar z aktivniho shared file zaznamu nebo initials fallbacku
     */
    private function avatar(int $userId, string $fallback): AdminThumbnail
    {
        $file = $this->files->findActiveImageForEntity('system.users', $userId, 'thumbnail');
        if ($file === null) {
            return $this->thumbnails->fallback($fallback, 'compact', 'circle');
        }

        return $this->thumbnails->image(
            module: 'system.users',
            imageId: (string) $file['id'],
            fallback: $fallback,
            size: 'compact',
            shape: 'circle',
            presentation: 'datagrid',
        );
    }
}
