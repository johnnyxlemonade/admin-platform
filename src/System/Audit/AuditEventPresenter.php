<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Prevadi auditni udalosti na lokalizovane texty, aktery a ikony pro administraci
 */
final class AuditEventPresenter
{
    public function __construct(
        private readonly AuditEventPresentationRegistry $presentations,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Sklada lokalizovany popis udalosti z registrovane prezentace a payloadu
     *
     * @param array<string, mixed> $payload
     */
    public function description(string $eventCode, array $payload): string
    {
        $presentation = $this->presentations->presentation($eventCode);
        if ($presentation === null) {
            return $eventCode;
        }

        return $this->translator->get(
            $presentation->resolvedTranslationKey($payload),
            $presentation->resolvedParams($payload) ?? $this->scalarParams($payload),
        );
    }

    /**
     * Urci zobrazovane oznaceni aktera vcetne systemovych a externich udalosti
     *
     * @param array{actor_email:string|null,actor_first_name:string|null,actor_last_name:string|null,actor_user_id:int|null,...} $event
     */
    public function actor(array $event): string
    {
        if (($event['actor_type'] ?? '') !== 'user') {
            if (($event['actor_type'] ?? '') === 'external') {
                return $this->translator->get('audit.actor.external');
            }

            return $this->translator->get('audit.actor.system');
        }
        $name = trim(($event['actor_first_name'] ?? '') . ' ' . ($event['actor_last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        if ($event['actor_email'] !== null && $event['actor_email'] !== '') {
            return $event['actor_email'];
        }

        return $this->translator->get('audit.actor.unknown', ['id' => (string) $event['actor_user_id']]);
    }

    /**
     * Vybere ikonu registrovane udalosti nebo vychozi ikonu auditu
     */
    public function icon(string $eventCode): AdminIcon
    {
        return $this->presentations->presentation($eventCode)?->icon() ?? AdminIcon::ClockHistory;
    }

    /**
     * Urci zobrazovany cil udalosti z prezentace, payloadu nebo klice entity
     *
     * @param array<string, mixed> $payload
     */
    public function entityLabel(string $eventCode, string $entityKey, array $payload): string
    {
        $label = $this->presentations->presentation($eventCode)?->resolvedEntityLabel($entityKey, $payload);
        if ($label !== null) {
            return $label;
        }

        $target = $payload['user'] ?? null;
        if (is_scalar($target) && (string) $target !== '') {
            return (string) $target;
        }

        $module = $payload['module'] ?? null;
        if (str_starts_with($eventCode, 'system.module_') && is_string($module) && $module !== '') {
            return $this->moduleLabel($module);
        }

        return '#' . $entityKey;
    }

    /**
     * Lokalizuje nazev modulu, pokud ma registrovanou prezentaci
     */
    public function moduleLabel(string $moduleCode): string
    {
        $presentation = $this->presentations->modulePresentation($moduleCode);

        return $presentation === null ? $moduleCode : $this->translator->get($presentation->nameKey());
    }

    /**
     * Ponecha z payloadu jen skalary pouzitelne jako parametry prekladu
     *
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function scalarParams(array $payload): array
    {
        $params = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $params[$key] = $value === null ? '' : (string) $value;
            }
        }

        return $params;
    }
}
