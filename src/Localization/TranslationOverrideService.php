<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslationSourceCatalogInterface;
use RuntimeException;

/**
 * Ridi auditovane mutace explicitnich translation overrides nad package source
 */
final class TranslationOverrideService
{
    public function __construct(
        private readonly TranslationOverrideModel $overrides,
        private readonly TranslationSourceCatalogInterface $sources,
        private readonly LocalizationConfig $config,
        private readonly TransactionalEventProcessor $events,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Uklada override jen pro podporovany locale a existujici canonical source key
     */
    public function set(string $locale, string $group, string $key, string $value): void
    {
        [$locale, $group, $key] = $this->identity($locale, $group, $key);
        if (!$this->sourceExists($group, $key)) {
            throw new RuntimeException('translations.validation.source_key_not_found');
        }

        $existing = $this->overrides->value($locale, $group, $key);
        if ($existing === $value) {
            return;
        }
        if ($existing === null && ($value === '' || $value === $this->sourceValue($locale, $group, $key))) {
            return;
        }

        $actor = $this->actor();
        $operation = $existing === null ? 'translations.override.create' : 'translations.override.update';
        $event = $existing === null ? 'system.translations.override_created' : 'system.translations.override_updated';
        $this->events->execute(new AuditOperation('system.translations', $operation, AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($locale, $group, $key, $value, $existing, $event): void {
            if (!$this->overrides->saveValue($locale, $group, $key, $value)) {
                throw new RuntimeException('translations.validation.override_not_saved');
            }
            $this->overrides->incrementRevision($locale, $group);
            $events->record(new DomainEvent(
                $event,
                'system.translations',
                'translation_override',
                $this->entityKey($locale, $group, $key),
                [
                    'locale' => $locale,
                    'group' => $group,
                    'key' => $key,
                    'operation' => $existing === null ? 'create' : 'update',
                    'override_existed' => $existing !== null,
                    'value_length' => mb_strlen($value),
                ],
            ));
        });
    }

    /**
     * Odstrani override a po commitu vrati resolve na aktualni package source
     */
    public function remove(string $locale, string $group, string $key): void
    {
        $this->removeMany([['locale' => $locale, 'group' => $group, 'key' => $key]]);
    }

    /**
     * Odstrani existujici overrides v jedne auditovane transakci
     *
     * @param list<array{locale:string,group:string,key:string}> $identities
     */
    public function removeMany(array $identities): void
    {
        $requested = [];
        foreach ($identities as $identity) {
            [$locale, $group, $key] = $this->identity($identity['locale'], $identity['group'], $identity['key']);
            $requested[$locale . "\0" . $group . "\0" . $key] = [
                'locale' => $locale,
                'group' => $group,
                'key' => $key,
            ];
        }

        $valuesByGroup = [];
        foreach ($requested as $identity) {
            $groupIdentity = $identity['locale'] . "\0" . $identity['group'];
            $valuesByGroup[$groupIdentity] ??= $this->overrides->group($identity['locale'], $identity['group']);
        }

        $existing = [];
        foreach ($requested as $identity) {
            $values = $valuesByGroup[$identity['locale'] . "\0" . $identity['group']];
            if (array_key_exists($identity['key'], $values)) {
                $existing[] = $identity;
            }
        }
        if ($existing === []) {
            return;
        }

        $revisionGroups = [];
        foreach ($existing as $identity) {
            $revisionGroups[$identity['locale'] . "\0" . $identity['group']] = [
                'locale' => $identity['locale'],
                'group' => $identity['group'],
            ];
        }

        $actor = $this->actor();
        $this->events->execute(new AuditOperation('system.translations', 'translations.override.remove', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($existing, $revisionGroups): void {
            foreach ($existing as $identity) {
                if (!$this->overrides->remove($identity['locale'], $identity['group'], $identity['key'])) {
                    throw new RuntimeException('translations.validation.override_not_removed');
                }
                $events->record(new DomainEvent(
                    'system.translations.override_removed',
                    'system.translations',
                    'translation_override',
                    $this->entityKey($identity['locale'], $identity['group'], $identity['key']),
                    [
                        'locale' => $identity['locale'],
                        'group' => $identity['group'],
                        'key' => $identity['key'],
                        'operation' => 'remove',
                        'override_existed' => true,
                    ],
                ));
            }
            foreach ($revisionGroups as $group) {
                $this->overrides->incrementRevision($group['locale'], $group['group']);
            }
        });
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function identity(string $locale, string $group, string $key): array
    {
        $locale = trim($locale);
        $group = trim($group);
        $key = trim($key);
        if (!in_array($locale, $this->config->supportedLocales, true)) {
            throw new RuntimeException('translations.validation.unsupported_locale');
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/', $group) !== 1 || preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/', $key) !== 1) {
            throw new RuntimeException('translations.validation.invalid_identity');
        }

        return [$locale, $group, $key];
    }

    /**
     * Overuje canonical key napric fyzickymi source resources bez locale fallbacku
     */
    private function sourceExists(string $group, string $key): bool
    {
        foreach ($this->sources->entries() as $entry) {
            if ($entry->group === $group && $entry->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nacita fyzickou source hodnotu pro vybrany locale bez runtime fallbacku
     */
    private function sourceValue(string $locale, string $group, string $key): ?string
    {
        $values = $this->sources->lines($locale, $group);

        return $values[$key] ?? null;
    }

    /**
     * Vytvari stabilni auditni identitu bez ukladani prelozeneho textu
     */
    private function entityKey(string $locale, string $group, string $key): string
    {
        return rawurlencode($locale) . ':' . rawurlencode($group) . ':' . rawurlencode($key);
    }

    /**
     * Vyzaduje lokalniho aktora pro auditovanou zmenu hodnoty
     */
    private function actor(): AuthenticatedUser
    {
        return $this->actors->requireLocalUser();
    }
}
