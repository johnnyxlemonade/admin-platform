<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

/**
 * Materializuje dashboardove providery z aktualniho request scope
 */
final class DashboardWidgetResolver
{
    /** @var array<string, DashboardWidgetRegistration>|null */
    private array|null $registrations = null;

    /**
     * Nastavuje root katalog a aktualni scoped container
     */
    public function __construct(
        private readonly DashboardWidgetRegistry $registry,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Vraci registraci widgetu podle kodu
     */
    public function registration(string $widgetCode): DashboardWidgetRegistration|null
    {
        return $this->registrations()[$widgetCode] ?? null;
    }

    /**
     * Vraci vsechny registrace serazene podle vychoziho rozlozeni
     *
     * @return list<DashboardWidgetRegistration>
     */
    public function all(): array
    {
        $registrations = array_values($this->registrations());
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
     * Materializuje registrace pouze uvnitr aktualniho scope
     *
     * @return array<string, DashboardWidgetRegistration>
     */
    private function registrations(): array
    {
        if ($this->registrations !== null) {
            return $this->registrations;
        }

        $registrations = [];
        foreach ($this->registry->providerServiceIds() as $serviceId) {
            $provider = $this->container->get($serviceId);
            if (!$provider instanceof DashboardWidgetProviderInterface) {
                throw new RuntimeException(sprintf(
                    'Dashboard provider service "%s" must implement %s.',
                    $serviceId,
                    DashboardWidgetProviderInterface::class,
                ));
            }
            foreach ($provider->definitions() as $definition) {
                $code = $definition->code();
                if (isset($registrations[$code])) {
                    throw new InvalidArgumentException(sprintf('Dashboard widget "%s" is already registered.', $code));
                }
                $registrations[$code] = new DashboardWidgetRegistration($definition, $provider);
            }
        }

        return $this->registrations = $registrations;
    }
}
