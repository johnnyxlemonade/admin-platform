<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Sklada preklady administrace do hierarchie pro klientsky loader
 */
final class ClientTranslationCatalog
{
    /**
     * Nastavuje prekladac, ktery poskytuje zdrojove skupiny
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * Prevadi plochou skupinu prekladu do hierarchie pro prohlizec
     *
     * @return array<string, mixed>
     */
    public function export(string $group, string $locale): array
    {
        $catalog = [];

        foreach ($this->translator->group($group, $locale) as $key => $value) {
            $this->put($catalog, $key, $value);
        }

        return $catalog;
    }

    /**
     * Vklada hodnotu pod teckove rozdeleny klic do katalogu
     *
     * @param array<string, mixed> $catalog
     */
    private function put(array &$catalog, string $key, string $value): void
    {
        $parts = explode('.', $key);
        $target = & $catalog;

        foreach ($parts as $part) {
            if ($part === '') {
                return;
            }

            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }

            $target = & $target[$part];
        }

        $target = $value;
    }
}
