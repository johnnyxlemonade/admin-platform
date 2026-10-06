<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

/**
 * Nese overeny vyber management zaznamu pro jeden exportni pozadavek
 */
final readonly class NotificationsExportSelection
{
    private const MAXIMUM_SIZE = 100;

    /**
     * @param list<int> $ids
     */
    private function __construct(
        private array $ids,
    ) {}

    /**
     * Prijima pouze neprazdny seznam kladnych celoiselnych ID bez duplicit
     */
    public static function fromInput(mixed $input): ?self
    {
        if (!is_array($input) || $input === [] || count($input) > self::MAXIMUM_SIZE) {
            return null;
        }

        $ids = [];
        foreach ($input as $id) {
            if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
                return null;
            }
            $ids[] = (int) $id;
        }

        if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            return null;
        }

        return new self($ids);
    }

    /**
     * Zpristupnuje unikatni ID ve formularovem poradi
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->ids;
    }
}
