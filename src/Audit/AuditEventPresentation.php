<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Nastavuje zobrazeni auditni udalosti v administraci
 */
final readonly class AuditEventPresentation
{
    /**
     * Vytvori zobrazeni udalosti s volitelnou ikonou a resolverem obsahu
     */
    public function __construct(
        private string $eventCode,
        private string $translationKey,
        private AdminIcon|null $icon = null,
        private AuditEventPresentationPayloadResolverInterface|null $payloadResolver = null,
    ) {}

    /**
     * Vrati kod auditni udalosti
     */
    public function eventCode(): string
    {
        return $this->eventCode;
    }

    /**
     * Vrati vychozi prekladovy klic udalosti
     */
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Vrati volitelnou ikonu udalosti
     */
    public function icon(): AdminIcon|null
    {
        return $this->icon;
    }

    /**
     * Vrati prekladovy klic upraveny podle obsahu udalosti
     *
     * @param array<string, mixed> $payload
     */
    public function resolvedTranslationKey(array $payload): string
    {
        return $this->payloadResolver?->translationKey($this->translationKey, $payload) ?? $this->translationKey;
    }

    /**
     * Vrati parametry prekladu upravene podle obsahu udalosti
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>|null
     */
    public function resolvedParams(array $payload): array|null
    {
        return $this->payloadResolver?->params($payload);
    }

    /**
     * Vrati popisek entity upraveny podle obsahu udalosti
     *
     * @param array<string, mixed> $payload
     */
    public function resolvedEntityLabel(string $entityKey, array $payload): string|null
    {
        return $this->payloadResolver?->entityLabel($entityKey, $payload);
    }
}
