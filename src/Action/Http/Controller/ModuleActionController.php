<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Http\Controller;

use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Action\ModuleActionDispatcher;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Obsluhuje HTTP pozadavky pro moduleaction
 */
final class ModuleActionController
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly ModuleActionDispatcher $actions,
        private readonly AdminAuthorizationResponseHandler $authorizationResponses,
        private readonly AdminUiLocale $locale,
        private readonly TranslatorInterface $translator,
        private readonly ModulePageRegistry $pages,
        private readonly AdminModuleRouteResolver $routes,
        private readonly Responses $responses,
        private readonly UrlGenerator $urls,
        private readonly FlashBagInterface $flash,
    ) {}

    /**
     * Zpracovava krok entity v HTTP toku administrace
     */
    public function entity(string $module, int $id, ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond($module, $id, $request);
    }

    /**
     * Vytvari novy zaznam z dat pozadavku
     */
    public function create(string $module, ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond($module, null, $request);
    }

    /**
     * Zpracovava krok respond v HTTP toku administrace
     */
    private function respond(string $module, ?int $id, ServerRequestInterface $request): ResponseInterface
    {
        $this->locale->activate(null, (string) (new RequestData($request))->cookie('lemonade_locale', ''));
        try {
            $result = $this->actions->dispatch($module, $id, (new RequestData($request))->jsonPayload());
        } catch (ModuleActionException $exception) {
            if ($exception->status() === HttpStatusCode::FORBIDDEN->value) {
                if ($this->authorizationResponses->wantsJson($request)) {
                    $code = $exception->errorCode() === AdminErrorCode::LOCAL_ACTOR_REQUIRED->value
                        ? AdminErrorCode::LOCAL_ACTOR_REQUIRED
                        : AdminErrorCode::PERMISSION_DENIED;

                    return $this->responses->json($this->authorizationResponses->forbiddenPayload($code), HttpStatusCode::FORBIDDEN->value);
                }
                $this->authorizationResponses->flashForbidden($this->flash);

                return $this->responses->redirect($this->urls->route('admin.dashboard'));
            }
            if ($exception->statusCode() === HttpStatusCode::NOT_FOUND && $exception->error() === AdminErrorCode::NOT_FOUND) {
                return $this->responses->json($this->authorizationResponses->recordNotFoundPayload(), HttpStatusCode::NOT_FOUND->value);
            }
            if ($exception->statusCode() === HttpStatusCode::CONFLICT && $exception->error() === AdminErrorCode::LOCK_CONFLICT) {
                return $this->responses->json(
                    $this->authorizationResponses->moduleActionConflictPayload($exception),
                    HttpStatusCode::CONFLICT->value,
                );
            }
            return $this->responses->json(['success' => false, 'error' => ['code' => $exception->errorCode(), 'message' => $exception->getMessage()]], $exception->status());
        }
        if (!$result->successful()) {
            return $this->responses->json(['success' => false, 'errors' => $this->localizedErrors($result->errors()), 'input' => $result->input()], HttpStatusCode::UNPROCESSABLE_ENTITY->value);
        }

        $data = $result->data();
        if ($id === null && isset($data['id']) && (int) $data['id'] > 0) {
            $entityId = (int) $data['id'];
            if ($this->pages->hasEditor($this->routes->resolve($module)->code())) {
                $data['editUrl'] ??= $this->urls->route($this->routeName($module, 'edit'), ['module' => $module, 'id' => $entityId]);
                $data['actionUrl'] ??= $this->urls->route($this->routeName($module, 'ajax.entity'), ['module' => $module, 'id' => $entityId]);
            } else {
                $data['successUrl'] ??= $this->urls->route($this->routeName($module, 'index'), ['module' => $module]);
            }
        }

        $messageKey = $result->messageKey();

        return $this->responses->json([
            'success' => true,
            'messageKey' => $messageKey,
            'message' => $messageKey === null ? null : $this->translator->get($messageKey),
            'data' => $data,
            'refreshGrid' => $result->refreshGrid(),
            'refreshPage' => $result->refreshPage(),
            'feedbackType' => $result->feedbackType(),
        ]);
    }

    /**
     * Zpracovava krok localizederrors v HTTP toku administrace
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    private function localizedErrors(array $errors): array
    {
        return array_map(fn(string $error): string => $this->translator->get($error), $errors);
    }

    /**
     * Urci route transportu podle ownershipu pozadovaneho modulu
     */
    private function routeName(string $segment, string $action): string
    {
        return $this->routes->managementRouteName($segment, $action);
    }
}
