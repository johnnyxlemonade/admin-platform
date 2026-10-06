<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Modules;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Audit\AuditEventPresenter;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class ModulesFeatureAuditPresentationTest extends TestCase
{
    /**
     * Overuje, ze vsechny feature auditni udalosti maji lokalizovany popis misto raw kodu
     */
    public function testFeatureAuditEventsResolveToLocalizedLabels(): void
    {
        $catalog = require dirname(__DIR__, 4) . '/src/System/Modules/Resources/lang/en/modules.php';
        $presentations = new AuditEventPresentationRegistry();
        foreach (['enabled', 'disabled', 'synchronized'] as $event) {
            $presentations->register(new AuditEventPresentation(
                'system.module_feature_' . $event,
                'modules.audit.feature_' . $event,
                AdminIcon::Boxes,
            ));
        }
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static function (string $key) use ($catalog): string {
            $segments = explode('.', $key);
            $value = $catalog;
            foreach (array_slice($segments, 1) as $segment) {
                $value = $value[$segment];
            }

            return $value;
        });
        $presenter = new AuditEventPresenter($presentations, $translator);

        self::assertSame('Module feature enabled.', $presenter->description('system.module_feature_enabled', []));
        self::assertSame('Module feature disabled.', $presenter->description('system.module_feature_disabled', []));
        self::assertSame('Module feature synchronized.', $presenter->description('system.module_feature_synchronized', []));
    }
}
