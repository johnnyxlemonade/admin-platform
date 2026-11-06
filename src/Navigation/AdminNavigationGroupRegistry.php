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
            AdminNavigationGroup::Cms->value => new AdminNavigationGroupDefinition(
                code: AdminNavigationGroup::Cms->value,
                nameKey: 'admin.navigation.cms',
                order: 40,
                icon: AdminIcon::JournalText,
            ),
            AdminNavigationGroup::Content->value => new AdminNavigationGroupDefinition(
                code: AdminNavigationGroup::Content->value,
                nameKey: 'admin.navigation.content',
                order: 45,
                icon: AdminIcon::Boxes,
            ),
            AdminNavigationGroup::System->value => new AdminNavigationGroupDefinition(
                code: AdminNavigationGroup::System->value,
                nameKey: 'admin.navigation.system',
                order: 50,
                icon: AdminIcon::Gear,
            ),
            AdminNavigationGroup::Settings->value => new AdminNavigationGroupDefinition(
                code: AdminNavigationGroup::Settings->value,
                nameKey: 'admin.navigation.settings',
                order: 60,
                icon: AdminIcon::Sliders,
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
