<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Validation;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

/**
 * Overuje serializaci vybranych opravneni proti canonical katalogu
 */
final class RolePermissionsRule implements ValidationRuleInterface
{
    /**
     * Nastavuje katalog povolenych kodu opravneni
     */
    public function __construct(private readonly PermissionCatalogRegistry $catalog) {}

    /**
     * Odmitne nezname nebo chybne serializovane hodnoty editoru pred mutaci role
     */
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);
        if ($value === null) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $code => $selected) {
            if (!is_string($code) || !in_array((string) $selected, ['0', '1'], true) || $this->catalog->definition($code) === null) {
                return false;
            }
        }

        return true;
    }
}
