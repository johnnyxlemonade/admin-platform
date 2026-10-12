<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminNavigationGroup;
use RuntimeException;

/**
 * Registruje skupiny navigace podle jejich kodu
 */
final class AdminNavigationGroupRegistry
{
    /** @var array<string, AdminNavigationGroupDefinition> */
    private array $definitions = [];

    /**
     * Pripravuje canonical skupiny navigace administrace
     */
    public function __construct()
    {
        $this->definitions = [
            AdminNavigationGroup::Content->value => new AdminNavigationGroupDefinition(
                AdminNavigationGroup::Content->value,
                'admin.navigation.content',
                40,
                AdminIcon::JournalText,
            ),
            AdminNavigationGroup::System->value => new AdminNavigationGroupDefinition(
                AdminNavigationGroup::System->value,
                'admin.navigation.system',
                50,
                AdminIcon::Gear,
            ),
            AdminNavigationGroup::Settings->value => new AdminNavigationGroupDefinition(
                AdminNavigationGroup::Settings->value,
                'admin.navigation.settings',
                60,
                AdminIcon::Sliders,
            ),
        ];
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
