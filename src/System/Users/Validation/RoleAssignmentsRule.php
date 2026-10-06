<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Validation;

use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

/**
 * Overuje tvar a existenci role zadane do Users editoru
 */
final class RoleAssignmentsRule implements ValidationRuleInterface
{
    /**
     * Nastavi Users service pro overeni aktivni role
     */
    public function __construct(private readonly UserService $users) {}

    /**
     * Prijme prazdny vstup nebo existujici kladny identifikator role
     *
     * @param array<string, mixed> $data
     */
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($param, $data);

        if ($value === null) {
            return true;
        }

        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value < 1) {
            return false;
        }

        return $this->users->roleExists((int) $value);
    }
}
