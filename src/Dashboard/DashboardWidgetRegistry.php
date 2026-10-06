<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;
use Iterator;
use IteratorIterator;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Traversable;

/**
 * Registruje dashboardove widgety
 */
final class DashboardWidgetRegistry
{
    /** @var array<string, DashboardWidgetRegistration> */
    private array $registrations = [];

    /** @var Iterator<array-key, DashboardWidgetProviderInterface>|null */
    private Iterator|null $pendingProviders = null;

    private bool $advancePendingProvider = false;

    /**
     * Prijme poskytovatele widgetu pro postupnou registraci
     *
     * @param iterable<DashboardWidgetProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        if ($providers instanceof Traversable) {
            $this->pendingProviders = $providers instanceof Iterator ? $providers : new IteratorIterator($providers);

            return;
        }

        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Zaregistruje vsechny widgety poskytovatele
     */
    public function registerProvider(DashboardWidgetProviderInterface $provider): void
    {
        foreach ($provider->definitions() as $definition) {
            $this->register($definition, $provider);
        }
    }

    /**
     * Zaregistruje widget pod jeho unikatnim kodem
     */
    public function register(DashboardWidgetDefinition $definition, DashboardWidgetProviderInterface $provider): void
    {
        $code = $definition->code();
        if (isset($this->registrations[$code])) {
            throw new InvalidArgumentException(sprintf('Dashboard widget "%s" is already registered.', $code));
        }

        $this->registrations[$code] = new DashboardWidgetRegistration($definition, $provider);
    }

    /**
     * Vrati registraci widgetu a nacita poskytovatele pouze podle potreby
     */
    public function registration(string $widgetCode): DashboardWidgetRegistration|null
    {
        if (!isset($this->registrations[$widgetCode])) {
            $this->materializeUntil($widgetCode);
        }

        return $this->registrations[$widgetCode] ?? null;
    }

    /**
     * Vrati vsechny registrace serazene podle vychoziho rozlozeni
     *
     * @return list<DashboardWidgetRegistration>
     */
    public function all(): array
    {
        $this->materializeUntil(null);
        $registrations = array_values($this->registrations);
        usort(
            $registrations,
            static fn(DashboardWidgetRegistration $left, DashboardWidgetRegistration $right): int => [
                $left->definition()->layout()->defaultOrder(),
                $left->definition()->code(),
            ] <=> [
                $right->definition()->layout()->defaultOrder(),
                $right->definition()->code(),
            ],
        );

        return $registrations;
    }

    /**
     * Nacte cekajici poskytovatele dokud nenajde widget nebo je nevycerpa
     */
    private function materializeUntil(?string $widgetCode): void
    {
        while ($this->pendingProviders !== null) {
            if ($this->advancePendingProvider) {
                $this->pendingProviders->next();
            }
            if (!$this->pendingProviders->valid()) {
                $this->pendingProviders = null;

                return;
            }

            $provider = $this->pendingProviders->current();
            $this->advancePendingProvider = true;
            $this->registerProvider($provider);
            if ($widgetCode !== null && isset($this->registrations[$widgetCode])) {
                return;
            }
        }
    }
}
