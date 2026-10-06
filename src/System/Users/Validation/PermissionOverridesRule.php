<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Validation;

use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

/**
 * Overuje tvar pozadovanych allow a deny permission overrides
 */
final class PermissionOverridesRule implements ValidationRuleInterface
{
    /**
     * Prijme pouze mapu permission kodu na editorove boolean hodnoty
     *
     * @param array<string, mixed> $data
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
        foreach ($value as $code => $effect) {
            if (!is_string($code) || !in_array((string) $effect, ['0', '1'], true)) {
                return false;
            }
        }
        return true;
    }
}
