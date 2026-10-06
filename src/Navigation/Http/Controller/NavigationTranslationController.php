<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation\Http\Controller;

use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationCatalog;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationGroup;
use Lemonade\Admin\Navigation\AdminNavigationItem;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Poskytuje prelozene popisky aktualni navigace pro klienta
 */
final class NavigationTranslationController
{
    /**
     * Nastavi lokalizaci, katalog, navigaci a JSON odpovedi
     */
    public function __construct(
        private readonly AdminUiLocale $uiLocale,
        private readonly ClientTranslationCatalog $catalog,
        private readonly AdminNavigation $navigation,
        private readonly Responses $responses,
    ) {}

    /**
     * Vrati prelozene skupiny a polozky dostupne aktualnimu uzivateli
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $requestedLocale = $requestData->query('locale');
        if ($requestedLocale !== null && (!is_string($requestedLocale) || !$this->uiLocale->supports($requestedLocale))) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::UNSUPPORTED_LOCALE->value],
            ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }
        $locale = $this->uiLocale->resolve(
            $requestedLocale,
            is_string($requestData->cookie('lemonade_locale')) ? $requestData->cookie('lemonade_locale') : null,
        );
        $groups = [];
        $items = [];
        foreach ($this->navigation->items() as $entry) {
            if ($entry instanceof AdminNavigationItem) {
                $items[] = ['id' => $entry->key(), 'label' => $this->label($entry->labelKey(), $locale)];

                continue;
            }

            if ($entry instanceof AdminNavigationGroup) {
                $groups[] = ['id' => $entry->key(), 'label' => $this->label($entry->labelKey(), $locale)];
                foreach ($entry->items() as $item) {
                    $items[] = ['id' => $item->key(), 'label' => $this->label($item->labelKey(), $locale)];
                }
            }
        }

        return $this->responses->json(['groups' => $groups, 'items' => $items])
            ->withHeader('Cache-Control', 'private, no-cache')
            ->withHeader('Vary', 'Cookie');
    }

    /**
     * Vrati prelozeny popisek podle lokalizacniho klice
     */
    private function label(string $key, string $locale): string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);
        $value = $group === null ? [] : $this->catalog->export($group, $locale);
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $key;
            }

            $value = $value[$part];
        }

        return is_string($value) ? $value : $key;
    }
}
