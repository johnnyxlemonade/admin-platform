<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http;

use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Editor\Lock\EditorLockConflictMessage;
use Lemonade\Admin\Editor\Lock\EditorLockOwner;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Prevadi chyby autorizace na odpovedi administrace
 */
final class AdminAuthorizationResponseHandler
{
    public const FLASH_KEY = 'admin.message';
    public const MESSAGE_KEY = 'admin.authorization.forbidden';
    public const RECORD_NOT_FOUND_MESSAGE_KEY = 'admin.record.not_found';
    public const RECORD_LOCKED_MESSAGE_KEY = 'admin.editor.record_locked';
    public const RECORD_LOCKED_BY_MESSAGE_KEY = 'admin.editor.record_locked_by';
    public const RECORD_CONFLICT_MESSAGE_KEY = 'admin.editor.record_conflict';

    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly HttpRequestInspector $requests,
        private readonly AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Rozhoduje, zda plati podminka wantsjson
     */
    public function wantsJson(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        $basePath = preg_quote($this->routing->basePath, '#');

        return str_starts_with($path, $this->routing->path('/api/'))
            || preg_match('#^' . $basePath . '/[^/]+/ajax(?:/|$)#D', $path) === 1
            || $this->requests->wantsJson($request);
    }

    /**
     * Zpracovava krok flashforbidden v HTTP toku administrace
     */
    public function flashForbidden(FlashBagInterface $flash): void
    {
        $flash->set(self::FLASH_KEY, ['type' => 'warning', 'key' => self::MESSAGE_KEY]);
    }

    /**
     * Zpracovava krok flashrecordnotfound v HTTP toku administrace
     */
    public function flashRecordNotFound(FlashBagInterface $flash): void
    {
        $flash->set(self::FLASH_KEY, ['type' => 'warning', 'key' => self::RECORD_NOT_FOUND_MESSAGE_KEY]);
    }

    /**
     * Zpracovava krok flashrecordlocked v HTTP toku administrace
     */
    public function flashRecordLocked(FlashBagInterface $flash, ?EditorLockOwner $owner = null, ?EditorLockConflictMessage $message = null): void
    {
        $messageKey = $this->lockConflictMessageKey($owner, $message);
        $messageParams = $this->lockConflictMessageParams($owner, $message);
        $flash->set(self::FLASH_KEY, [
            'type' => 'warning',
            'key' => $messageKey,
            'params' => $messageParams,
        ]);
    }

    /**
     * Zpracovava krok flashrecordconflict v HTTP toku administrace
     */
    public function flashRecordConflict(FlashBagInterface $flash): void
    {
        $flash->set(self::FLASH_KEY, ['type' => 'warning', 'key' => self::RECORD_CONFLICT_MESSAGE_KEY]);
    }

    /**
     * Zpracovava krok forbiddenpayload v HTTP toku administrace
     * @return array{success:false,error:array{code:string,messageKey:string},messageKey:string}
     */
    public function forbiddenPayload(AdminErrorCode $code = AdminErrorCode::PERMISSION_DENIED): array
    {
        return [
            'success' => false,
            'error' => ['code' => $code->value, 'messageKey' => self::MESSAGE_KEY],
            'messageKey' => self::MESSAGE_KEY,
        ];
    }

    /**
     * Zpracovava krok recordnotfoundpayload v HTTP toku administrace
     * @return array{success:false,error:array{code:string,messageKey:string},messageKey:string}
     */
    public function recordNotFoundPayload(): array
    {
        return [
            'success' => false,
            'error' => ['code' => AdminErrorCode::NOT_FOUND->value, 'messageKey' => self::RECORD_NOT_FOUND_MESSAGE_KEY],
            'messageKey' => self::RECORD_NOT_FOUND_MESSAGE_KEY,
        ];
    }

    /**
     * Zpracovava krok recordconflictpayload v HTTP toku administrace
     * @return array{success:false,error:array{code:string,messageKey:string},messageKey:string}
     */
    public function recordConflictPayload(): array
    {
        return [
            'success' => false,
            'error' => ['code' => AdminErrorCode::LOCK_CONFLICT->value, 'messageKey' => self::RECORD_CONFLICT_MESSAGE_KEY],
            'messageKey' => self::RECORD_CONFLICT_MESSAGE_KEY,
        ];
    }

    /**
     * Zpracovava krok moduleactionconflictpayload v HTTP toku administrace
     * @return array{success:false,error:array{code:string,messageKey:string,lockedBy?:array{userId:int|null,displayName:string}|null},messageKey:string,messageParams?:array<string,string>}
     */
    public function moduleActionConflictPayload(ModuleActionException $exception): array
    {
        if ($exception->isRecordConflict()) {
            return $this->recordConflictPayload();
        }

        return $this->recordLockedPayload($exception->lockedBy());
    }

    /**
     * Zpracovava krok recordlockedpayload v HTTP toku administrace
     * @return array{success:false,error:array{code:string,messageKey:string,lockedBy:array{userId:int|null,displayName:string}|null},messageKey:string,messageParams:array<string,string>}
     */
    public function recordLockedPayload(?EditorLockOwner $owner = null, ?EditorLockConflictMessage $message = null): array
    {
        $lockedBy = $owner === null ? null : ['userId' => $owner->userId, 'displayName' => $owner->displayName];
        $messageKey = $this->lockConflictMessageKey($owner, $message);
        $messageParams = $this->lockConflictMessageParams($owner, $message);

        return [
            'success' => false,
            'error' => ['code' => AdminErrorCode::LOCK_CONFLICT->value, 'messageKey' => $messageKey, 'lockedBy' => $lockedBy],
            'messageKey' => $messageKey,
            'messageParams' => $messageParams,
        ];
    }

    /**
     * Zpracovava krok lockconflictmessagekey v HTTP toku administrace
     */
    private function lockConflictMessageKey(?EditorLockOwner $owner, ?EditorLockConflictMessage $message): string
    {
        if ($message !== null && ($owner !== null || $message->key !== self::RECORD_LOCKED_BY_MESSAGE_KEY)) {
            return $message->key;
        }

        return $owner === null
            ? self::RECORD_CONFLICT_MESSAGE_KEY
            : self::RECORD_LOCKED_BY_MESSAGE_KEY;
    }

    /**
     * Zpracovava krok lockconflictmessageparams v HTTP toku administrace
     * @return array<string, string>
     */
    private function lockConflictMessageParams(?EditorLockOwner $owner, ?EditorLockConflictMessage $message): array
    {
        if ($message !== null && ($owner !== null || $message->key !== self::RECORD_LOCKED_BY_MESSAGE_KEY)) {
            return $message->params;
        }

        return $owner === null ? [] : ['name' => $owner->displayName];
    }
}
