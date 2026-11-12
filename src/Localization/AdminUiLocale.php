<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Aktivuje a rozhoduje jazyk uzivatelskeho rozhrani administrace
 */
final readonly class AdminUiLocale
{
    public const array SUPPORTED_LOCALES = ['cs', 'en'];

    private const string SESSION_KEY = 'admin.ui.locale';

    /**
     * Nastavuje relaci a prekladac administrace
     */
    public function __construct(
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
     * Vraci podporovane jazyky rozhrani administrace
     *
     * @return list<string>
     */
    public function supportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Rozhoduje, zda je predany jazyk podporovan administraci
     */
    public function supports(string $locale): bool
    {
        return $this->normalizeSupportedLocale($locale) !== null;
    }

    /**
     * Vraci vychozi jazyk administrace
     */
    private function defaultLocale(): string
    {
        return self::SUPPORTED_LOCALES[0];
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
