<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use InvalidArgumentException;

/**
 * Registruje skupiny prekladu vystavene klientovi administrace
 */
final class ClientTranslationGroupRegistry
{
    /** @var array<string, true> */
    private array $groups = [];

    /**
     * Registruje validni skupinu prekladu pro klientsky export
     */
    public function register(string $group): void
    {
        $group = trim($group);
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $group) !== 1) {
            throw new InvalidArgumentException('Client translation group must be a normalized group name.');
        }

        $this->groups[$group] = true;
    }

    /**
     * Rozhoduje, zda je skupina prekladu zaregistrovana
     */
    public function has(string $group): bool
    {
        return isset($this->groups[$group]);
    }

    /**
     * Vraci abecedne serazene registrovane skupiny prekladu
     *
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = array_keys($this->groups);
        sort($groups);

        return $groups;
    }
}
