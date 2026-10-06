<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Dashboard\Models\DashboardWidgetPreferenceModel;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;

/**
 * Sklada rozlozeni osobniho dashboardu
 */
final class DashboardLayoutService
{
    /**
     * Nastavi sluzby pro osobni rozlozeni dashboardu
     */
    public function __construct(
        private readonly DashboardWidgetRegistry $widgets,
        private readonly DashboardWidgetAccessPolicy $access,
        private readonly DashboardWidgetPreferenceModel $preferences,
        private readonly TransactionalEventProcessor $events,
    ) {}

    /**
     * Vrati osobne pripnute widgety dostupne pro kontext
     *
     * @return list<DashboardResolvedWidget>
     */
    public function forContext(DashboardWidgetContext $context): array
    {
        $preferences = [];
        foreach ($this->preferences->activeForOwnerKey($context->ownerKey()) as $preference) {
            $preferences[(string) $preference['widget_code']] = $preference;
        }

        $resolved = [];
        foreach ($this->widgets->all() as $registration) {
            $definition = $registration->definition();
            if (!$this->access->canView($definition)) {
                continue;
            }
            $preference = $preferences[$definition->code()] ?? null;
            if ($preference === null || (int) $preference['personal_pinned'] !== 1) {
                continue;
            }
            $resolved[] = new DashboardResolvedWidget($registration, (int) $preference['position'], $this->resolvedSize($preference, $definition));
        }
        usort($resolved, static fn(DashboardResolvedWidget $left, DashboardResolvedWidget $right): int => [$left->position(), $left->registration()->definition()->layout()->defaultOrder(), $left->registration()->definition()->code()] <=> [$right->position(), $right->registration()->definition()->layout()->defaultOrder(), $right->registration()->definition()->code()]);

        return $resolved;
    }

    /**
     * Pripne dostupny widget na zadanou pozici
     */
    public function pin(DashboardWidgetContext $context, string $widgetCode, int $position): void
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Dashboard widget position must not be negative.');
        }
        $registration = $this->widgets->registration($widgetCode);
        if ($registration === null || !$this->access->canView($registration->definition())) {
            throw new InvalidArgumentException('Dashboard widget is not available.');
        }

        $this->events->execute($this->operation($context, 'dashboard.preference.pin'), function (TransactionalEventCollector $events) use ($context, $widgetCode, $position, $registration): void {
            $layout = $registration->definition()->layout();
            $id = $this->preferences->restoreOrCreatePersonalPin($context->ownerKey(), $context->localUser()->id(), $widgetCode, $position, $layout->defaultSize());
            $existing = $this->preferences->findByOwnerKey($context->ownerKey(), $widgetCode);
            $size = is_array($existing) && is_string($existing['size'] ?? null) && in_array(DashboardWidgetSize::tryFrom($existing['size']), $layout->supportedSizes(), true) ? $existing['size'] : $layout->defaultSize()->value;
            $this->preferences->update($id, ['personal_pinned' => 1, 'size' => $size]);
            $events->record($this->event($context, 'system.dashboard.preference_pinned', (string) $id, ['widgetCode' => $widgetCode, 'position' => $position, 'size' => $size]));
        });
    }

    /**
     * Odebere osobni pripnuti widgetu
     */
    public function unpin(DashboardWidgetContext $context, string $widgetCode): void
    {
        $preference = $this->preferences->findByOwnerKey($context->ownerKey(), $widgetCode);
        if ($preference === null || $preference['deleted_at'] !== null) {
            return;
        }

        $this->events->execute($this->operation($context, 'dashboard.preference.unpin'), function (TransactionalEventCollector $events) use ($context, $preference, $widgetCode): void {
            $this->preferences->delete((int) $preference['id']);
            $events->record($this->event($context, 'system.dashboard.preference_unpinned', (string) $preference['id'], ['widgetCode' => $widgetCode]));
        });
    }

    /**
     * Zmeni velikost pripnuteho widgetu
     */
    public function updateSize(DashboardWidgetContext $context, string $widgetCode, DashboardWidgetSize $size): void
    {
        $registration = $this->widgets->registration($widgetCode);
        if ($registration === null || !$this->access->canView($registration->definition()) || !in_array($size, $registration->definition()->layout()->supportedSizes(), true)) {
            throw new InvalidArgumentException('Dashboard widget is not available.');
        }
        foreach ($this->forContext($context) as $widget) {
            if ($widget->registration()->definition()->code() !== $widgetCode) {
                continue;
            }
            $this->events->execute($this->operation($context, 'dashboard.preference.size'), function (TransactionalEventCollector $events) use ($context, $widgetCode, $widget, $size): void {
                $id = $this->preferences->restoreOrCreateLayoutPreference($context->ownerKey(), $context->localUser()->id(), $widgetCode, $widget->position(), $size);
                $events->record($this->event($context, 'system.dashboard.preference_size_changed', (string) $id, ['widgetCode' => $widgetCode, 'size' => $size->value]));
            });

            return;
        }

        throw new InvalidArgumentException('Dashboard widget is not pinned.');
    }

    /**
     * Ulozi nove poradi pripnutych widgetu
     *
     * @param list<string> $widgetCodes
     */
    public function reorder(DashboardWidgetContext $context, array $widgetCodes): void
    {
        $resolved = $this->forContext($context);
        $expected = array_map(static fn(DashboardResolvedWidget $widget): string => $widget->registration()->definition()->code(), $resolved);
        if (count($widgetCodes) !== count(array_unique($widgetCodes)) || array_diff($widgetCodes, $expected) !== [] || array_diff($expected, $widgetCodes) !== []) {
            throw new InvalidArgumentException('Dashboard widget order must contain every effective widget exactly once.');
        }

        $byCode = [];
        foreach ($resolved as $widget) {
            $byCode[$widget->registration()->definition()->code()] = $widget;
        }
        $this->events->execute($this->operation($context, 'dashboard.preference.reorder'), function (TransactionalEventCollector $events) use ($context, $widgetCodes, $byCode): void {
            foreach ($widgetCodes as $position => $widgetCode) {
                $widget = $byCode[$widgetCode];
                $this->preferences->restoreOrCreateLayoutPreference($context->ownerKey(), $context->localUser()->id(), $widgetCode, $position, $widget->size());
            }
            $events->record($this->event($context, 'system.dashboard.preferences_reordered', $context->ownerKey(), ['count' => count($widgetCodes)]));
        });
    }

    /**
     * Vrati podporovanou velikost z preference nebo definice widgetu
     *
     * @param array<string, mixed>|null $preference
     */
    private function resolvedSize(array|null $preference, DashboardWidgetDefinition $definition): DashboardWidgetSize
    {
        $stored = $preference === null ? null : DashboardWidgetSize::tryFrom((string) ($preference['size'] ?? ''));

        $layout = $definition->layout();

        return $stored !== null && in_array($stored, $layout->supportedSizes(), true) ? $stored : $layout->defaultSize();
    }

    /**
     * Vytvori auditni operaci pro zmenu rozlozeni
     */
    private function operation(DashboardWidgetContext $context, string $operation): AuditOperation
    {
        return new AuditOperation('system.dashboard', $operation, AuditActor::user($context->localUser()->id()));
    }

    /**
     * Vytvori udalost pro zmenu rozlozeni
     *
     * @param array<string, bool|float|int|string|null> $payload
     */
    private function event(DashboardWidgetContext $context, string $code, string $entityKey, array $payload): DomainEvent
    {
        return new DomainEvent($code, 'system.dashboard', 'dashboard_widget_preference', $entityKey, $payload);
    }
}
