<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Http;

use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Presentation\AdminPageRenderer;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje JSON odpovedi pro AJAX konflikty administrace
 */
final class AdminResponseFactoryTest extends TestCase
{
    /**
     * Vraci vlastnika zamku jako canonical JSON payload AJAX akce
     */
    public function testRecordLockedJsonRequestReturnsOwnerPayloadWithoutRedirect(): void
    {
        $flash = $this->createMock(FlashBagInterface::class);
        $flash->expects(self::never())->method('set');

        $response = $this->factory($flash)->recordLocked(
            $this->jsonRequest(),
            '/admin/system/roles',
            new EditorLockOwner(42, 'Petr Novák'),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([
            'success' => false,
            'error' => [
                'code' => 'lock_conflict',
                'messageKey' => AdminAuthorizationResponseHandler::RECORD_LOCKED_BY_MESSAGE_KEY,
                'lockedBy' => ['userId' => 42, 'displayName' => 'Petr Novák'],
            ],
            'messageKey' => AdminAuthorizationResponseHandler::RECORD_LOCKED_BY_MESSAGE_KEY,
            'messageParams' => ['name' => 'Petr Novák'],
        ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Vraci zpravu o soubezne zmene jako canonical JSON payload AJAX akce
     */
    public function testRecordConflictJsonRequestReturnsPayloadWithoutRedirect(): void
    {
        $flash = $this->createMock(FlashBagInterface::class);
        $flash->expects(self::never())->method('set');

        $response = $this->factory($flash)->recordConflict(
            $this->jsonRequest(),
            '/admin/system/roles/edit/2',
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame([
            'success' => false,
            'error' => [
                'code' => 'lock_conflict',
                'messageKey' => AdminAuthorizationResponseHandler::RECORD_CONFLICT_MESSAGE_KEY,
            ],
            'messageKey' => AdminAuthorizationResponseHandler::RECORD_CONFLICT_MESSAGE_KEY,
        ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Vytvari JSON HTTP pozadavek administrace
     */
    private function jsonRequest(): ServerRequest
    {
        return (new ServerRequest('GET', '/admin/system/roles/edit/2'))
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Vytvari response factory s realnym flash handlerem a HTTP response builderem
     *
     * @param FlashBagInterface&MockObject $flash
     */
    private function factory(FlashBagInterface $flash): AdminResponseFactory
    {
        /** @var AdminPageRenderer $pages */
        $pages = (new ReflectionClass(AdminPageRenderer::class))->newInstanceWithoutConstructor();
        /** @var AdminUiLocale $locale */
        $locale = (new ReflectionClass(AdminUiLocale::class))->newInstanceWithoutConstructor();
        /** @var UrlGenerator $urls */
        $urls = (new ReflectionClass(UrlGenerator::class))->newInstanceWithoutConstructor();
        $responseFactory = new Psr17Factory();

        return new AdminResponseFactory(
            authorization: new AdminAuthorizationResponseHandler(
                (new ReflectionClass(HttpRequestInspector::class))->newInstanceWithoutConstructor(),
                new AdminRoutingConfiguration('/admin'),
            ),
            pages: $pages,
            locale: $locale,
            translator: $this->createMock(TranslatorInterface::class),
            flash: $flash,
            urls: $urls,
            responses: new Responses(new ResponseBuilder($responseFactory, $responseFactory)),
        );
    }
}
