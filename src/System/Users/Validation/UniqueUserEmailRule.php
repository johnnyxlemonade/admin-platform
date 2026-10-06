<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Validation;

use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Validation\Rule\ValidationRuleInterface;

/**
 * Overuje format a unikatnost normalizovaneho e-mailu uzivatele
 */
final class UniqueUserEmailRule implements ValidationRuleInterface
{
    /**
     * Nastavi Users service pro overeni unikatnosti e-mailu
     */
    public function __construct(private readonly UserService $users) {}

    /**
     * Overi validni e-mail mimo zaznam editovany v aktualnim editoru
     *
     * @param array<string, mixed> $data
     */
    public function validate(mixed $value, ?string $param, array $data): bool
    {
        unset($data);

        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && $param !== null
            && ctype_digit($param)
            && $this->users->emailAvailable(LocalAuthenticationProvider::normalizeEmail($value), (int) $param);
    }
}
