<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Translations;

use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Localization\TranslationSourceCatalogInterface;
use Lemonade\Framework\Localization\TranslationSourceContribution;
use Lemonade\Framework\Localization\TranslationSourceEntry;
use Lemonade\Framework\Localization\TranslationSourceProvenance;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje groupovou projekci zdrojovych a resolved prekladu
 */
final class TranslationsCatalogTest extends TestCase
{
    /**
     * Overuje, ze jedna group mapa pokryje vsechny jeji klice
     */
    public function testItBuildsTheProjectionFromGroupMaps(): void
    {
        $sources = new TranslationsCatalogSource([
            $this->entry('messages', 'first', 'První', 'shared'),
            $this->entry('messages', 'second', 'Druhý', 'shared'),
            $this->entry('forms', 'label', 'Štítek', 'forms'),
        ]);
        $overrides = new TranslationsCatalogOverrides([
            'messages' => ['second' => 'Vlastní druhý'],
            'forms' => [],
        ]);
        $translator = new TranslationsCatalogTranslator([
            'messages' => ['first' => 'První', 'second' => 'Vlastní druhý'],
            'forms' => ['label' => 'Štítek'],
        ]);

        $entries = (new TranslationsCatalog($sources, $translator, $overrides))->entries('cs');

        self::assertSame(['messages', 'forms'], $overrides->requestedGroups);
        self::assertSame(['messages', 'forms'], $translator->requestedGroups);
        self::assertSame('První', $entries[0]['effective']);
        self::assertSame('Vlastní druhý', $entries[1]['effective']);
        self::assertTrue($entries[1]['overridden']);
        self::assertSame('forms', $entries[2]['owner']);
    }

    /**
     * Vytvari zdrojovy zaznam s deklarovanym ownerem
     */
    private function entry(string $group, string $key, string $value, string $owner): TranslationSourceEntry
    {
        return new TranslationSourceEntry(
            locale: 'cs',
            group: $group,
            key: $key,
            value: $value,
            contributions: [
                new TranslationSourceContribution(
                    value: $value,
                    provenance: new TranslationSourceProvenance('resource', '/translations', $owner),
                ),
            ],
        );
    }
}

/**
 * Poskytuje pevny zdrojovy katalog pro groupovou projekci
 */
final class TranslationsCatalogSource implements TranslationSourceCatalogInterface
{
    /**
     * @param list<TranslationSourceEntry> $entries
     */
    public function __construct(private readonly array $entries) {}

    public function locales(): array
    {
        return ['cs'];
    }

    public function groups(string $locale): array
    {
        return ['forms', 'messages'];
    }

    public function entries(?string $locale = null): array
    {
        return $locale === null || $locale === 'cs' ? $this->entries : [];
    }

    public function lines(string $locale, string $group): array
    {
        return [];
    }
}

/**
 * Zaznamenava groupova cteni explicitnich hodnot
 */
final class TranslationsCatalogOverrides implements TranslationOverrideProviderInterface
{
    /**
     * @var list<string>
     */
    public array $requestedGroups = [];

    /**
     * @param array<string, array<string, string>> $values
     */
    public function __construct(private readonly array $values) {}

    public function groups(string $locale): array
    {
        return array_keys($this->values);
    }

    public function group(string $locale, string $group): array
    {
        $this->requestedGroups[] = $group;

        return $this->values[$group] ?? [];
    }
}

/**
 * Zaznamenava groupove resolved hodnoty bez per-key resolve
 */
final class TranslationsCatalogTranslator implements TranslatorInterface
{
    /**
     * @var list<string>
     */
    public array $requestedGroups = [];

    /**
     * @param array<string, array<string, string>> $values
     */
    public function __construct(private readonly array $values) {}

    public function setLocale(?string $locale): self
    {
        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        throw new \LogicException('Catalog projection must resolve groups instead of individual keys.');
    }

    public function group(string $group, ?string $locale = null): array
    {
        $this->requestedGroups[] = $group;

        return $this->values[$group] ?? [];
    }

    public function all(?string $locale = null): array
    {
        return $this->values;
    }
}
