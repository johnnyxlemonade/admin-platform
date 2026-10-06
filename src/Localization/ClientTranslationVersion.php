<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\FileTranslator;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use ReflectionClass;

/**
 * Pocita verzi klientskych prekladu z jejich zdrojovych souboru
 */
final class ClientTranslationVersion
{
    /** @var array<string, int> */
    private array $revisions = [];

    /**
     * Nastavuje cesty aplikace, lokalizaci a registrovane zdroje prekladu
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly LocalizationConfig $config,
        private readonly TranslationResourceRegistry $resources,
        private readonly TranslationOverrideModel $overrides,
        private readonly TranslationOverrideCache $cache,
    ) {
        $this->cache->registerRequestForgetter($this->forget(...));
    }

    /**
     * Vraci verze skupiny pro vsechny podporovane jazyky
     *
     * @return array<string, string>
     */
    public function versions(string $group): array
    {
        $versions = [];
        foreach ($this->config->supportedLocales as $locale) {
            $versions[$locale] = $this->version($group, $locale);
        }

        return $versions;
    }

    /**
     * Vypocita otisk zdroju skupiny pro pozadovany jazyk
     */
    public function version(string $group, string $locale): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, "client-i18n\0{$group}\0{$locale}\0");

        foreach ($this->paths($group, $locale) as $path) {
            if (!is_file($path)) {
                continue;
            }

            hash_update($hash, $path . "\0");
            $fileHash = hash_file('sha256', $path);
            if ($fileHash === false) {
                throw new \RuntimeException(sprintf('Unable to fingerprint translation resource "%s".', $path));
            }

            hash_update($hash, $fileHash);
            hash_update($hash, "\0");
        }

        foreach ($this->locales($locale) as $candidate) {
            hash_update($hash, "override\0{$candidate}\0{$this->revision($candidate, $group)}\0");
        }

        return hash_final($hash);
    }

    /**
     * Vraci soubory, ktere ovlivnuji otisk skupiny a jazyka
     *
     * @return list<string>
     */
    private function paths(string $group, string $locale): array
    {
        $locales = $this->locales($locale);
        $paths = [];
        $translatorPath = (new ReflectionClass(FileTranslator::class))->getFileName();
        if ($translatorPath === false) {
            throw new \LogicException('Unable to locate the framework translator.');
        }

        foreach ($locales as $candidate) {
            $paths[] = $this->context->path('src/Language/' . $candidate . '/' . $group . '.php');
            $paths[] = dirname($translatorPath) . '/Language/' . $candidate . '/' . $group . '.php';
            foreach ($this->resources->directories() as $directory) {
                $paths[] = $directory . '/' . $candidate . '/' . $group . '.php';
            }
            $paths[] = $this->context->appPath('Language/' . $candidate . '/' . $group . '.php');
        }

        return $paths;
    }

    /**
     * Vraci locale kandidaty ve stejnem fallback poradi jako frameworkovy prekladac
     *
     * @return list<string>
     */
    private function locales(string $locale): array
    {
        return $locale === $this->config->fallbackLocale
            ? [$locale]
            : [$this->config->fallbackLocale, $locale];
    }

    /**
     * Vraci jednou nactenou revizi overrides pro jazyk a skupinu
     */
    private function revision(string $locale, string $group): int
    {
        $key = $locale . "\0" . $group;

        return $this->revisions[$key] ??= $this->cache->revision(
            $locale,
            $group,
            fn(): int => $this->overrides->revision($locale, $group),
        );
    }

    /**
     * Odstrani request-local revision zmenene override skupiny
     */
    public function forget(string $locale, string $group): void
    {
        unset($this->revisions[$locale . "\0" . $group]);
    }
}
