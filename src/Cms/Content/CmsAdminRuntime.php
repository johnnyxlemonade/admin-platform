<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalUserDirectory;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Cms\Content\CmsContentRuntime;

/**
 * Pripojuje CMS transakce k Admin auditu a lokalni identite
 */
final readonly class CmsAdminRuntime implements CmsContentRuntime
{
    /**
     * Nastavuje auditni transakce, jazyky a lokalni uzivatele
     */
    public function __construct(
        private TransactionalEventProcessor $events,
        private LanguageRegistry $languages,
        private LocalUserDirectory $users,
    ) {}

    /**
     * Provede CMS mutaci pod canonical lokalni auditni identitou
     *
     * @template T
     * @param callable(\Lemonade\Cms\Content\CmsContentEvents):T $mutation
     * @return T
     */
    public function execute(int $actorUserId, callable $mutation): mixed
    {
        return $this->events->execute(
            AuditActor::user($actorUserId),
            static fn(TransactionalEventCollector $events): mixed => $mutation(new CmsAdminEvents($events)),
        );
    }

    /**
     * Overi, zda je content locale aktualne aktivni
     */
    public function isEnabledLocale(string $locale): bool
    {
        foreach ($this->languages->enabledLocales() as $language) {
            if ($language['code'] === $locale) {
                return true;
            }
        }

        return false;
    }

    /**
     * Overi existenci nesmazaneho lokalniho vlastnika
     */
    public function ownerExists(int $userId): bool
    {
        return $this->users->exists($userId);
    }
}
