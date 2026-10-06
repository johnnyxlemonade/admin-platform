<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Localization\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro forgotpassword
 */
final class ForgotPasswordController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminUiLocale $uiLocale,
        private readonly ClientTranslationVersion $translationVersion,
        private readonly AuthPageRenderer $pages,
        private readonly TranslatorInterface $translator,
        private readonly AdminBranding $branding,
    ) {}

    /**
     * Zpracovava krok form v HTTP toku administrace
     */
    public function form(ServerRequestInterface $request): ResponseInterface
    {
        $this->uiLocale->activate(
            (string) (new RequestData($request))->query('locale', ''),
            (string) (new RequestData($request))->cookie('lemonade_locale', ''),
        );

        return $this->page($request);
    }

    /**
     * Zpracovava krok submit v HTTP toku administrace
     */
    public function submit(ServerRequestInterface $request): ResponseInterface
    {
        $requestData = new RequestData($request);
        $this->uiLocale->activate(null, (string) $requestData->cookie('lemonade_locale', ''));

        $email = trim((string) $requestData->post('email', ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->page($request, 'auth.forgot.errors.email', 'error', $email, HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }

        return $this->page($request, 'auth.forgot.unavailable', 'info', $email);
    }

    /**
     * Zpracovava krok page v HTTP toku administrace
     */
    private function page(
        ServerRequestInterface $request,
        ?string $messageKey = null,
        ?string $messageType = null,
        string $email = '',
        ?int $status = null,
    ): ResponseInterface {
        $status ??= HttpStatusCode::OK->value;
        return $this->pages->render('admin-auth::forgot-password', [
            'title' => $this->branding->applicationName . ' – ' . $this->translator->get('auth.forgot.title'),
            'email' => $email,
            'locale' => $this->uiLocale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', '')),
            'messageKey' => $messageKey,
            'messageType' => $messageType,
            'clientTranslationVersion' => $this->translationVersion,
        ], $status);
    }
}
