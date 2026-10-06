<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenNames;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Kontroluje HTTP pozadavek pred zpracovanim administrace
 */
final class AdminLoginAjaxCsrfMiddleware implements MiddlewareInterface
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly CsrfViewHelper $csrf,
        private readonly TranslatorInterface $translator,
        private readonly HttpRequestInspector $requestInspector,
        private readonly Psr17Factory $responses,
    ) {}

    /**
     * Zpracovava krok process v HTTP toku administrace
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->requestInspector->wantsJson($request) || $response->getStatusCode() !== 419) {
            return $response;
        }

        $token = $response->getHeaderLine(CsrfTokenNames::HEADER);
        if ($token === '') {
            $token = $this->csrf->token();
        }

        $message = $this->translator->get('auth.errors.csrf');

        $payload = json_encode([
            'success' => false,
            'errors' => [
                'login' => $message,
            ],
            'message' => $message,
            'csrf' => [
                'name' => $this->csrf->fieldName(),
                'value' => $token,
            ],
        ], JSON_THROW_ON_ERROR);

        return $this->responses
            ->createResponse(419)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader(CsrfTokenNames::HEADER, $token)
            ->withBody($this->responses->createStream($payload));
    }
}
