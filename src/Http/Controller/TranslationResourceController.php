<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Controller;

use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationCatalog;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro translationresource
 */
final class TranslationResourceController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminUiLocale $uiLocale,
        private readonly ClientTranslationCatalog $catalog,
        private readonly ClientTranslationGroupRegistry $groups,
        private readonly ClientTranslationVersion $versions,
        private readonly Responses $responses,
        private readonly Router $router,
    ) {}

    /**
     * Vykresluje nebo vraci data pozadovane administracni stranky
     */
    public function index(): ResponseInterface
    {
        $resources = [];
        foreach ($this->groups->groups() as $group) {
            $resources[] = [
                'namespace' => $group,
                'source' => $this->router->url('admin.resources.i18n', ['group' => $group]) . '?locale={locale}&v={version}',
                'versions' => $this->versions->versions($group),
            ];
        }

        return $this->responses->json(['resources' => $resources])
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    /**
     * Zpracovava krok show v HTTP toku administrace
     */
    public function show(string $group, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->groups->has($group)) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::TRANSLATION_GROUP_NOT_FOUND->value],
            ], HttpStatusCode::NOT_FOUND->value);
        }

        $requestData = new RequestData($request);
        $requestedLocale = $requestData->query('locale');
        $requestedVersion = $requestData->query('v');
        if ($requestedLocale !== null && (!is_string($requestedLocale) || !$this->uiLocale->supports($requestedLocale))) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::UNSUPPORTED_LOCALE->value],
            ], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }

        $locale = $this->uiLocale->resolve(
            $requestedLocale,
            is_string($requestData->cookie('lemonade_locale')) ? $requestData->cookie('lemonade_locale') : null,
        );
        if ($requestedVersion !== null && (!is_string($requestedVersion) || $requestedLocale === null || !hash_equals($this->versions->version($group, $locale), $requestedVersion))) {
            return $this->responses->json([
                'error' => ['code' => AdminErrorCode::TRANSLATION_VERSION_NOT_FOUND->value],
            ], HttpStatusCode::NOT_FOUND->value)
                ->withHeader('Cache-Control', 'no-store');
        }
        $catalog = $this->catalog->export($group, $locale);
        $cacheControl = $requestedVersion === null ? 'public, max-age=300' : 'public, max-age=31536000, immutable';

        $response = $this->responses->json($catalog)
            ->withHeader('Cache-Control', $cacheControl);

        return $requestedLocale === null ? $response->withHeader('Vary', 'Cookie') : $response;
    }
}
