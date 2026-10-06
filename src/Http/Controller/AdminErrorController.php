<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Controller;

use Lemonade\Admin\Http\AdminResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro adminerror
 */
final class AdminErrorController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly AdminResponseFactory $responses,
    ) {}

    /**
     * Vraci odpoved pro nenalezeny administracni cil
     */
    public function notFound(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->notFound($request);
    }
}
