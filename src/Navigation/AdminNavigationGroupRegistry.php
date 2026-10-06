<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use InvalidArgumentException;
use RuntimeException;

/**
 * Registruje skupiny navigace podle jejich kodu
 */
final class AdminNavigationGroupRegistry
{
    /** @var array<string, AdminNavigationGroupDefinition> */
    private array $definitions = [];

    /**
     * Registruje validni a jedinecnou skupinu navigace
     */
    public function register(AdminNavigationGroupDefinition $definition): void
    {
        $code = $definition->code();
        if (preg_match('/^[a-z][a-z0-9-]*$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('Admin navigation group "%s" is invalid.', $code));
        }
        if (isset($this->definitions[$code])) {
            throw new InvalidArgumentException(sprintf('Admin navigation group "%s" is already registered.', $code));
        }

        $this->definitions[$code] = $definition;
    }

    /**
     * Rozhoduje, zda je skupina zaregistrovana
     */
    public function has(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    /**
     * Vraci definici zaregistrovane skupiny navigace
     */
    public function definition(string $code): AdminNavigationGroupDefinition
    {
        if (!$this->has($code)) {
            throw new RuntimeException(sprintf('Admin navigation group "%s" is not registered.', $code));
        }

        return $this->definitions[$code];
    }
}
