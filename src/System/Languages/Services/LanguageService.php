<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Services;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Flag\AdminCountryFlag;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
use Lemonade\Admin\System\Languages\Models\LanguageRecord;
use Lemonade\Framework\Database\Exception\DatabaseException;
use RuntimeException;

/**
 * Ridi mutace jazyku, audit a kontrolu enabled default invariant
 */
final class LanguageService
{
    /**
     * Nastavuje persistenci, auditni transakce a overeni lokalniho aktora
     */
    public function __construct(
        private readonly LanguageModel $languages,
        private readonly TransactionalEventProcessor $events,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Nacita jazyk podle interniho identifikatoru nebo oznamuje jeho nedostupnost
     */
    public function detail(int $id): LanguageRecord
    {
        $language = $this->languages->findLanguage($id);
        if ($language === null) {
            throw new RuntimeException('languages.validation.not_found');
        }

        return $language;
    }

    /**
     * Vytvari jazyk s jedinecnym business klicem code a overuje default invariant
     */
    public function create(string $code, string $name, string $flagCode, bool $enabled, int $sortOrder): int
    {
        $actor = $this->actor();
        $code = LanguageModel::normalizeCode($code);
        $flagCode = AdminCountryFlag::from(strtoupper(trim($flagCode)))->countryCode();
        if ($code === '' || $this->languages->codeExists($code)) {
            throw new RuntimeException('languages.validation.code_taken');
        }

        try {
            return $this->events->execute(new AuditOperation('system.languages', 'languages.editor.create', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($code, $name, $flagCode, $enabled, $sortOrder): int {
                $id = $this->languages->createLanguage($code, $name, $flagCode, $enabled, $sortOrder);
                $this->assertEnabledDefaultExists();
                $events->record(new DomainEvent('system.languages.created', 'system.languages', 'language', (string) $id, ['code' => $code, 'name' => $name]));

                return $id;
            });
        } catch (DatabaseException $exception) {
            if ($this->languages->codeExists($code)) {
                throw new RuntimeException('languages.validation.code_taken', previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * Uklada pouze jmeno, vlajku a poradi bez zmeny code nebo stavu
     */
    public function update(int $id, string $name, string $flagCode, int $sortOrder): void
    {
        $actor = $this->actor();
        $language = $this->detail($id);
        $flagCode = AdminCountryFlag::from(strtoupper(trim($flagCode)))->countryCode();
        $changes = [];
        if ($language->name() !== $name) {
            $changes['name'] = $name;
        }
        if ($language->flagCode() !== $flagCode) {
            $changes['flag_code'] = $flagCode;
        }
        if ($language->sortOrder() !== $sortOrder) {
            $changes['sort_order'] = $sortOrder;
        }
        if ($changes === []) {
            return;
        }

        $this->events->execute(new AuditOperation('system.languages', 'languages.editor.save', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $name, $flagCode, $sortOrder, $changes, $language): void {
            $this->languages->updateLanguage($id, $name, $flagCode, $sortOrder);
            $this->assertEnabledDefaultExists();
            $events->record(new DomainEvent(
                'system.languages.updated',
                'system.languages',
                'language',
                (string) $id,
                ['code' => $language->code(), 'changes' => $changes],
            ));

        });
    }

    /**
     * Meni stav jazyka a odmita deaktivaci vychoziho zaznamu
     */
    public function setEnabled(int $id, bool $enabled): void
    {
        $actor = $this->actor();
        $language = $this->detail($id);
        if ($language->enabled() === $enabled) {
            return;
        }
        if (!$enabled && $language->isDefault()) {
            throw new RuntimeException('languages.validation.default_cannot_be_disabled');
        }
        $eventCode = $enabled ? 'system.languages.enabled' : 'system.languages.disabled';
        $operation = $enabled ? 'languages.enable' : 'languages.disable';
        $this->events->execute(new AuditOperation('system.languages', $operation, AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $enabled, $eventCode, $language): void {
            $this->languages->updateEnabled($id, $enabled);
            $this->assertEnabledDefaultExists();
            $events->record(new DomainEvent(
                $eventCode,
                'system.languages',
                'language',
                (string) $id,
                ['code' => $language->code(), 'name' => $language->name()],
            ));
        });
    }

    /**
     * Nastavuje enabled jazyk jako jediny vychozi zaznam
     */
    public function setDefault(int $id): void
    {
        $actor = $this->actor();
        $language = $this->detail($id);
        if (!$language->enabled()) {
            throw new RuntimeException('languages.validation.default_must_be_enabled');
        }
        if ($language->isDefault() && $this->languages->enabledDefaultCount() === 1) {
            return;
        }
        $this->events->execute(new AuditOperation('system.languages', 'languages.set_default', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $language): void {
            $this->languages->clearDefaults();
            $this->languages->setDefault($id);
            $this->assertEnabledDefaultExists();
            $events->record(new DomainEvent(
                'system.languages.default_changed',
                'system.languages',
                'language',
                (string) $id,
                ['code' => $language->code(), 'name' => $language->name()],
            ));
        });
    }

    /**
     * Kontroluje, ze po mutaci existuje prave jeden enabled vychozi jazyk
     */
    private function assertEnabledDefaultExists(): void
    {
        if ($this->languages->enabledDefaultCount() !== 1) {
            throw new RuntimeException('languages.validation.default_invariant');
        }
    }

    /**
     * Vyzaduje lokalniho uzivatele pro auditovanou mutaci
     */
    private function actor(): AuthenticatedUser
    {
        return $this->actors->requireLocalUser();
    }
}
