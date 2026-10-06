<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Exceptions;

use RuntimeException;

/**
 * Prenasi prekladovy klic Users bezpecnostniho nebo authorization odmitnuti
 */
final class UserSafetyException extends RuntimeException
{
    /**
     * Nastavi prekladovy klic vysvetlujici odmitnutou Users mutaci
     */
    public function __construct(private readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }
}
