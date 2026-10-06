<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Models;

/**
 * Nese nemenny detail systemoveho jazyka pro editor a service
 */
final class LanguageRecord
{
    public function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly string $flagCode,
        private readonly bool $enabled,
        private readonly bool $default,
        private readonly int $sortOrder,
    ) {}

    /**
     * Prevadi databazovy radek na typed detailovy zaznam
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            flagCode: (string) $row['flag_code'],
            enabled: (int) $row['enabled'] === 1,
            default: (int) $row['is_default'] === 1,
            sortOrder: (int) $row['sort_order'],
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function flagCode(): string
    {
        return $this->flagCode;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
