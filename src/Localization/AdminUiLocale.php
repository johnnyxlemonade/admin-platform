<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Aktivuje a rozhoduje jazyk uzivatelskeho rozhrani administrace
 */
final readonly class AdminUiLocale
{
    private const SESSION_KEY = 'admin.ui.locale';

    /**
     * Nastavuje konfiguraci podporovanych jazyku, relaci a prekladac
     */
    public function __construct(
        private LocalizationConfig $config,
        private SessionInterface $session,
        private TranslatorInterface $translator,
    ) {}

    /**
     * Aktivuje podporovany jazyk z pozadavku, cookie nebo relace a vraci jej
     */
    public function activate(?string $requestedLocale = null, ?string $cookieLocale = null): string
    {
        $this->session->start();

        $requested = $this->normalizeSupportedLocale($requestedLocale)
            ?? $this->normalizeSupportedLocale($cookieLocale);
        if ($requested !== null) {
            $this->session->set(self::SESSION_KEY, $requested);
        }

        $stored = $this->session->get(self::SESSION_KEY);
        $locale = $this->normalizeSupportedLocale(is_string($stored) ? $stored : null)
            ?? $this->defaultLocale();

        $this->translator->setLocale($locale);

        return $locale;
    }

    /**
     * Vraci podporovany jazyk bez zmeny relace a prekladace
     */
    public function resolve(?string $requestedLocale = null, ?string $cookieLocale = null): string
    {
        return $this->normalizeSupportedLocale($requestedLocale)
            ?? $this->normalizeSupportedLocale($cookieLocale)
            ?? $this->defaultLocale();
    }

    /**
     * Vraci normalizovane podporovane jazyky bez duplicit
     *
     * @return list<string>
     */
    public function supportedLocales(): array
    {
        $locales = [];

        foreach ($this->config->supportedLocales as $locale) {
            if (!is_string($locale)) {
                continue;
            }

            $normalized = strtolower(trim($locale));
            if ($normalized !== '' && !in_array($normalized, $locales, true)) {
                $locales[] = $normalized;
            }
        }

        return $locales;
    }

    /**
     * Rozhoduje, zda je predany jazyk podporovan konfiguraci
     */
    public function supports(string $locale): bool
    {
        return $this->normalizeSupportedLocale($locale) !== null;
    }

    /**
     * Vraci podporovany vychozi jazyk konfigurace
     */
    private function defaultLocale(): string
    {
        $default = $this->normalizeSupportedLocale($this->config->defaultLocale);

        return $default ?? $this->supportedLocales()[0];
    }

    /**
     * Normalizuje jazyk a vraci jej pouze pokud je podporovan
     */
    private function normalizeSupportedLocale(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = strtolower(trim($locale));

        return in_array($normalized, $this->supportedLocales(), true) ? $normalized : null;
    }
}
