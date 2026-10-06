<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;

/**
 * Zpristupnuje persistovane overrides frameworkovemu translation runtime
 */
final class DatabaseTranslationOverrideProvider implements TranslationOverrideProviderInterface
{
    /** @var array<string, array<string, string>> */
    private array $groups = [];

    /**
     * Nastavuje model overrides a persistentni cache jejich skupin
     */
    public function __construct(
        private readonly TranslationOverrideModel $overrides,
        private readonly TranslationOverrideCache $cache,
    ) {
        $this->cache->registerRequestForgetter($this->forget(...));
    }

    /**
     * Vraci skupiny s explicitnimi overrides pro vybrany locale
     *
     * @return list<string>
     */
    public function groups(string $locale): array
    {
        return $this->overrides->groups($locale);
    }

    /**
     * Vraci explicitni overrides jedne skupiny pres L1 a L2 cache
     *
     * @return array<string, string>
     */
    public function group(string $locale, string $group): array
    {
        $key = $locale . "\0" . $group;

        return $this->groups[$key] ??= $this->cache->group(
            $locale,
            $group,
            fn(): array => $this->overrides->group($locale, $group),
        );
    }

    /**
     * Odstrani request-local hodnotu zmenene override skupiny
     */
    public function forget(string $locale, string $group): void
    {
        unset($this->groups[$locale . "\0" . $group]);
    }
}
