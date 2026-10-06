<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Framework\Routing\Router;
use LogicException;

/**
 * Sbirka relativnich registraru rout administrace
 */
final class AdminRouteRegistrarRegistry
{
    /** @var array<string, AdminRouteRegistrarInterface> */
    private array $registrars = [];

    /**
     * Pridava capability registrar s jedinecnym identifikatorem
     */
    public function register(AdminRouteRegistrarInterface $registrar): void
    {
        if (isset($this->registrars[$registrar->id()])) {
            throw new LogicException(sprintf('Admin route registrar "%s" is already registered.', $registrar->id()));
        }

        $this->registrars[$registrar->id()] = $registrar;
    }

    /**
     * Registruje serazene relativni routy do prefixovane router skupiny
     */
    public function registerRoutes(Router $router): void
    {
        $registrars = $this->registrars;
        uasort($registrars, static function (AdminRouteRegistrarInterface $left, AdminRouteRegistrarInterface $right): int {
            $priority = $left->priority() <=> $right->priority();

            return $priority !== 0 ? $priority : strcmp($left->id(), $right->id());
        });

        foreach ($registrars as $registrar) {
            $registrar->registerRoutes($router);
        }
    }
}
