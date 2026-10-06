<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

/**
 * Upravuje zobrazeni auditni udalosti podle jejiho obsahu
 */
interface AuditEventPresentationPayloadResolverInterface
{
    /**
     * Vrati prekladovy klic pro konkretni auditni udalost
     *
     * @param array<string, mixed> $payload
     */
    public function translationKey(string $defaultTranslationKey, array $payload): string;

    /**
     * Vrati parametry pro preklad auditni udalosti
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    public function params(array $payload): array;

    /**
     * Vrati popisek entity auditni udalosti
     *
     * @param array<string, mixed> $payload
     */
    public function entityLabel(string $entityKey, array $payload): string|null;
}
