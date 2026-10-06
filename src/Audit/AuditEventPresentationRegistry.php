<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;

/**
 * Uchovava zobrazeni auditnich udalosti a modulu
 */
final class AuditEventPresentationRegistry
{
    /** @var array<string, AuditEventPresentation> */
    private array $presentations = [];

    /** @var array<string, AuditModulePresentation> */
    private array $modulePresentations = [];

    /**
     * Zaregistruje zobrazeni auditni udalosti pod jejim kodem
     */
    public function register(AuditEventPresentation $presentation): void
    {
        $eventCode = $presentation->eventCode();
        if (isset($this->presentations[$eventCode])) {
            throw new InvalidArgumentException(sprintf('Audit event presentation "%s" is already registered.', $eventCode));
        }

        $this->presentations[$eventCode] = $presentation;
    }

    /**
     * Vrati zobrazeni auditni udalosti pokud je zaregistrovane
     */
    public function presentation(string $eventCode): AuditEventPresentation|null
    {
        return $this->presentations[$eventCode] ?? null;
    }

    /**
     * Zaregistruje zobrazeni modulu pod jeho kodem
     */
    public function registerModule(AuditModulePresentation $presentation): void
    {
        $moduleCode = $presentation->moduleCode();
        if (isset($this->modulePresentations[$moduleCode])) {
            throw new InvalidArgumentException(sprintf('Audit module presentation "%s" is already registered.', $moduleCode));
        }

        $this->modulePresentations[$moduleCode] = $presentation;
    }

    /**
     * Vrati zobrazeni modulu pokud je zaregistrovane
     */
    public function modulePresentation(string $moduleCode): AuditModulePresentation|null
    {
        return $this->modulePresentations[$moduleCode] ?? null;
    }
}
