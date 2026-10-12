<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Deklaruje auditni prezentaci udalosti vlastnene administracnim modulem
 */
final readonly class AuditEventDefinition
{
    /**
     * Vytvori definici udalosti s lokalizovanym popiskem a volitelnou ikonou
     */
    public function __construct(
        public string $suffix,
        public string $translationKey,
        public AdminIcon|null $icon = null,
    ) {
        if ($this->suffix === '') {
            throw new InvalidArgumentException('Audit event suffix must not be empty.');
        }

        if ($this->translationKey === '') {
            throw new InvalidArgumentException('Audit event translation key must not be empty.');
        }
    }
}
