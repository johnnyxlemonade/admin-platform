<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Action\Presentation\AdminActionFormDefinition;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorBuilder;
use Lemonade\Admin\Editor\AdminEditor\Field\Field;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorFieldRenderer;
use Lemonade\Admin\Editor\AdminEditor\Rendering\AdminEditorRenderContext;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\LocaleUrlStrategyInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewHelpers;
use PHPUnit\Framework\TestCase;

/**
 * Overuje sdileny HTML kontrakt modalnich AdminEditor view
 */
final class AdminEditorModalViewTest extends TestCase
{
    /**
     * Overi stabilni ID, texty a action transport vsech modal variant
     */
    public function testSharedPartialPreservesModalContracts(): void
    {
        $view = new View(dirname(__DIR__, 4) . '/src/Resources/views');
        $helpers = $this->helpers();
        $editor = AdminEditorBuilder::create('modal-editor')
            ->form(new AdminActionFormDefinition(
                id: 'modal-editor-form',
                action: '/fallback',
                actionUrl: '/actions',
                actionKey: 'save',
                csrf: false,
            ))
            ->block(Field::text('title'))
            ->build();
        $common = [
            'helpers' => $helpers,
            'adminEditor' => $editor,
            'adminEditorContext' => new AdminEditorRenderContext(),
            'adminEditorFields' => new AdminEditorFieldRenderer($helpers),
        ];

        $languages = $view->render('components.admin-editor-modal', $common + [
            'modalId' => 'languages-modal-editor',
            'title' => 'Language title',
            'titleKey' => 'languages.editor.title',
            'description' => 'Language description',
            'descriptionKey' => 'languages.editor.description',
        ]);
        $notifications = $view->render('components.admin-editor-modal', $common + [
            'modalId' => 'notifications-modal-editor',
            'title' => 'Notification title',
            'titleKey' => 'notifications.editor.title',
            'description' => 'Notification description',
            'descriptionKey' => 'notifications.editor.description',
            'submitKey' => 'notifications.actions.publish',
        ]);
        $translations = $view->render('components.admin-editor-modal', $common + [
            'modalId' => 'translations-modal-editor',
            'title' => 'Translation title',
            'titleKey' => 'translations.editor.title',
            'description' => 'Translation description',
            'descriptionKey' => 'translations.editor.description',
        ]);
        $file = $view->render('components.admin-editor-modal', $common + [
            'modalId' => 'admin-file-presentation',
            'titleKey' => 'admin.file_upload.edit_title',
        ]);

        self::assertStringContainsString('id="languages-modal-editor-title"', $languages);
        self::assertStringContainsString('id="languages-modal-editor-description"', $languages);
        self::assertStringContainsString('id="notifications-modal-editor-title"', $notifications);
        self::assertStringContainsString('data-lemonade-i18n="notifications.actions.publish"', $notifications);
        self::assertStringContainsString('id="translations-modal-editor-description"', $translations);
        self::assertStringContainsString('id="admin-file-presentation-title"', $file);
        self::assertStringNotContainsString('admin-file-presentation-description', $file);
        self::assertStringContainsString('data-lemonade-form="modal-editor-form"', $file);
        self::assertStringContainsString('data-lemonade-url="/actions"', $file);
        self::assertStringContainsString('data-lemonade-action-key="save"', $file);
        self::assertStringContainsString('data-lemonade-modal-close="close"', $file);
        self::assertStringContainsString('data-lemonade-modal-close="cancel"', $file);
    }

    /**
     * Vytvori view helpers s deterministickym prekladovym kontraktem
     */
    private function helpers(): ViewHelpers
    {
        $session = $this->createMock(SessionInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(
            static fn(string $key): string => $key,
        );

        return new ViewHelpers(
            urlGenerator: new UrlGenerator(
                new Router(),
                null,
                $this->createMock(LocaleUrlStrategyInterface::class),
            ),
            csrf: new CsrfViewHelper(new CsrfTokenManager($session)),
            translator: $translator,
            config: new LocalizationConfig(
                'en',
                'en',
                ['en'],
                new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
        );
    }
}
