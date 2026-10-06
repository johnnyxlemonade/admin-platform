<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Localization\TranslationSourceCatalogInterface;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Sklada read model source prekladu a explicitnich override hodnot pro administraci
 */
final class TranslationsCatalog
{
    /**
     * Nastavuje source katalog, resolved runtime a persistovane overrides
     */
    public function __construct(
        private readonly TranslationSourceCatalogInterface $sources,
        private readonly TranslatorInterface $translator,
        private readonly TranslationOverrideProviderInterface $overrides,
    ) {}

    /**
     * Vraci projekci vsech source klicu pro presny locale bez skladani fallbacku
     *
     * @return list<array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool}>
     */
    public function entries(string $locale): array
    {
        $selectedByIdentity = [];
        foreach ($this->sources->entries($locale) as $entry) {
            $selectedByIdentity[$entry->group . "\0" . $entry->key] = $entry;
        }

        $entriesByGroup = [];
        foreach ($this->sources->entries() as $entry) {
            $identity = $entry->group . "\0" . $entry->key;
            if (isset($entriesByGroup[$entry->group][$identity])) {
                continue;
            }

            $entriesByGroup[$entry->group][$identity] = $entry;
        }

        $overrideValues = [];
        $effectiveValues = [];
        foreach (array_keys($entriesByGroup) as $group) {
            $overrideValues[$group] = $this->overrides->group($locale, $group);
            $effectiveValues[$group] = $this->translator->group($group, locale: $locale);
        }

        $entries = [];
        foreach ($entriesByGroup as $group => $groupEntries) {
            foreach ($groupEntries as $identity => $entry) {
                $sourceEntry = $selectedByIdentity[$identity] ?? null;
                $ownerEntry = $sourceEntry ?? $entry;
                $contribution = $ownerEntry->contributions[count($ownerEntry->contributions) - 1];
                $overrideValue = $overrideValues[$group][$entry->key] ?? null;
                $entries[] = [
                    'id' => $this->id($locale, $group, $entry->key),
                    'locale' => $locale,
                    'owner' => $contribution->provenance->owner ?? 'unknown',
                    'group' => $group,
                    'key' => $entry->key,
                    'source' => $sourceEntry === null ? '' : $sourceEntry->value,
                    'effective' => $effectiveValues[$group][$entry->key] ?? $group . '.' . $entry->key,
                    'overrideValue' => $overrideValue,
                    'overridden' => $overrideValue !== null,
                    'missingSource' => $sourceEntry === null,
                ];
            }
        }

        return $entries;
    }

    /**
     * Vraci owner hodnoty pouze z provenance zdrojovych prekladu
     *
     * @return list<string>
     */
    public function owners(): array
    {
        $owners = [];
        foreach ($this->sources->entries() as $entry) {
            $contribution = $entry->contributions[count($entry->contributions) - 1];
            $owner = $contribution->provenance->owner;
            if ($owner !== null) {
                $owners[$owner] = true;
            }
        }

        $owners = array_keys($owners);
        sort($owners);

        return $owners;
    }

    /**
     * Vyhleda source identitu editoru podle deterministickeho presentation identifikatoru
     *
     * @return array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool}|null
     */
    public function find(int $id): ?array
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    /**
     * Vyhleda source identity pro vybrane presentation identifikatory najednou
     *
     * @param list<int> $ids
     * @return array<int, array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool}>
     */
    public function findMany(array $ids): array
    {
        $remaining = array_fill_keys($ids, true);
        $translations = [];
        foreach ($this->sources->locales() as $locale) {
            foreach ($this->entries($locale) as $entry) {
                if (isset($remaining[$entry['id']])) {
                    $translations[$entry['id']] = $entry;
                    unset($remaining[$entry['id']]);
                }
            }
            if ($remaining === []) {
                break;
            }
        }

        return $translations;
    }

    /**
     * Vytvari presentation identifikator bez zmeny canonical persistence identity
     */
    private function id(string $locale, string $group, string $key): int
    {
        return abs(crc32($locale . "\0" . $group . "\0" . $key));
    }
}
