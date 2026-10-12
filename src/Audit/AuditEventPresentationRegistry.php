<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

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
     * Zaregistruje auditni prezentaci identity a udalosti administracniho modulu
     */
    public function registerModuleDefinition(AdminModuleDefinitionInterface $definition): void
    {
        $moduleCode = $definition->code();
        $metadata = $definition->adminMetadata();
        $this->registerModule(new AuditModulePresentation(
            moduleCode: $moduleCode,
            nameKey: $metadata->nameKey(),
            icon: $metadata->icon(),
        ));

        foreach ($definition->auditEventDefinitions() as $eventDefinition) {
            $this->register(new AuditEventPresentation(
                eventCode: $moduleCode . '.' . $eventDefinition->suffix,
                translationKey: $eventDefinition->translationKey,
                icon: $eventDefinition->icon,
            ));
        }
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
