<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification\Models;

use Generator;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\Identity\ActorIdentity;
use Lemonade\Admin\Notification\Contract\NotificationInboxRepositoryInterface;
use Lemonade\Admin\Notification\NotificationRecipientIdentity;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Uklada oznameni, jejich publikum, zobrazeni prijemcu a management projection
 */
final class NotificationModel extends Model implements NotificationInboxRepositoryInterface
{
    protected string $table = 'admin_notification';

    /**
     * Vrati stranku aktivnich oznameni viditelnych pro daneho prijemce
     *
     * @return array{rows:list<array{id:int,type:string,title:string,message:string,created_at:string,read_at:string|null,author_first_name:string|null,author_last_name:string|null,author_email:string|null}>,hasMore:bool}
     */
    public function inboxPageForRecipient(NotificationRecipientIdentity $recipient, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));
        $offset = ($page - 1) * $perPage;
        /** @var list<array{id:int,type:string,title:string,message:string,created_at:string,read_at:string|null,author_first_name:string|null,author_last_name:string|null,author_email:string|null}> $rows */
        $rows = $this->inboxQuery($recipient)->select(['n.id', 'n.type', 'n.title', 'n.message', 'n.created_at', 'nr.read_at'])->selectRaw('u.first_name AS author_first_name, u.last_name AS author_last_name, u.email AS author_email')->orderBy('n.id', 'DESC')->limit($perPage, $offset)->getArray();

        return ['rows' => $rows, 'hasMore' => $this->inboxQuery($recipient)->countAllResults() > $offset + count($rows)];
    }

    /**
     * Spocita neprectena oznameni viditelna pro daneho prijemce
     */
    public function unreadCountForRecipient(NotificationRecipientIdentity $recipient): int
    {
        return $this->inboxQuery($recipient)->where('nr.read_at', null)->countAllResults();
    }

    /**
     * Overi existenci neprecteneho oznameni bez nacteni celeho inboxu
     */
    public function hasUnreadForRecipient(NotificationRecipientIdentity $recipient): bool
    {
        return $this->inboxQuery($recipient)
            ->select('n.id')
            ->where('nr.read_at', null)
            ->limit(1)
            ->getArray() !== [];
    }

    /**
     * Zapise precteni jednoho viditelneho oznameni prijemcem
     */
    public function markReadForRecipient(int $notificationId, NotificationRecipientIdentity $recipient): bool
    {
        /** @var list<array{id:int}> $visible */
        $visible = $this->inboxQuery($recipient)->select('n.id')->where('n.id', $notificationId)->limit(1)->getArray();
        if ($visible === []) {
            return false;
        }
        $now = $this->now();
        return $this->db->query('INSERT INTO admin_notification_recipient(notification_id,recipient_key,recipient_user_id,read_at,created_at,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE read_at = COALESCE(read_at, VALUES(read_at)), recipient_user_id = COALESCE(recipient_user_id, VALUES(recipient_user_id)), updated_at = VALUES(updated_at)', [$notificationId, $recipient->key(), $recipient->localUserId(), $now, $now, $now]) !== false;
    }

    /**
     * Zapise precteni vsech viditelnych oznameni prijemcem
     */
    public function markAllReadForRecipient(NotificationRecipientIdentity $recipient): int
    {
        /** @var list<array{id:int}> $visible */
        $visible = $this->inboxQuery($recipient)->select('n.id')->where('nr.read_at', null)->getArray();
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $visible);
        if ($ids === []) {
            return 0;
        }
        foreach ($ids as $id) {
            $this->markReadForRecipient($id, $recipient);
        }
        return count($ids);
    }

    /**
     * Vytvori aktivni oznameni se zadanym autorem
     */
    public function createNotification(NotificationType $type, string $title, string $message, int $authorId): int
    {
        $now = $this->now();

        return (int) $this->insert([
            'type' => $type->value,
            'title' => $title,
            'message' => $message,
            'created_by_user_id' => $authorId,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Vrati oznameni vcetne publika pro administracni editor
     *
     * @return array{id:int,type:string,title:string,message:string,active:int,deleted_at:string|null,role_ids:list<int>,user_ids:list<int>,audience_users:list<array{id:int,email:string}>}|null
     */
    public function notificationForEditor(int $id): ?array
    {
        /** @var list<array{id:int,type:string,title:string,message:string,active:int,deleted_at:string|null}> $notifications */
        $notifications = $this->query()
            ->from('admin_notification')
            ->select(['id', 'type', 'title', 'message', 'active', 'deleted_at'])
            ->where('id', $id)
            ->limit(1)
            ->getArray();
        $notification = $notifications[0] ?? null;
        if ($notification === null) {
            return null;
        }

        /** @var list<array{role_id:int}> $roles */
        $roles = $this->query()
            ->from('admin_notification_audience_role')
            ->select('role_id')
            ->where('notification_id', $id)
            ->orderBy('role_id')
            ->getArray();
        /** @var list<array{id:int,email:string}> $users */
        $users = $this->query()
            ->from('admin_notification_audience_user')
            ->selectRaw('user_id AS id, user_email AS email')
            ->where('notification_id', $id)
            ->orderBy('user_email')
            ->getArray();

        return [
            ...$notification,
            'role_ids' => array_map(static fn(array $role): int => (int) $role['role_id'], $roles),
            'user_ids' => array_map(static fn(array $user): int => (int) $user['id'], $users),
            'audience_users' => $users,
        ];
    }

    /**
     * Upravi obsah existujiciho nesmazaneho oznameni
     */
    public function updateNotification(int $id, NotificationType $type, string $title, string $message): bool
    {
        return $this->query()
            ->from('admin_notification')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->set([
                'type' => $type->value,
                'title' => $title,
                'message' => $message,
                'updated_at' => $this->now(),
            ])
            ->update();
    }

    /**
     * Odstrani role i uzivatele z publika oznameni
     */
    public function clearAudience(int $notificationId): void
    {
        $this->query()->from('admin_notification_audience_role')->where('notification_id', $notificationId)->delete();
        $this->query()->from('admin_notification_audience_user')->where('notification_id', $notificationId)->delete();
    }

    /**
     * Odstrani zaznamy zobrazeni, aby se oznameni prijemcum ukazalo znovu
     */
    public function resetDisplayState(int $notificationId): void
    {
        $this->query()->from('admin_notification_recipient')->where('notification_id', $notificationId)->delete();
    }

    /**
     * Vrati lifecycle stav jednoho oznameni pro autorizovanou mutaci
     *
     * @return array{id:int,active:int,deleted_at:string|null}|null
     */
    public function lifecycleState(int $id): ?array
    {
        /** @var list<array{id:int,active:int,deleted_at:string|null}> $states */
        $states = $this->query()->from('admin_notification')->select(['id', 'active', 'deleted_at'])->where('id', $id)->limit(1)->getArray();
        return $states[0] ?? null;
    }

    /**
     * Vrati lifecycle stavy vybranych oznameni podle jejich ID
     *
     * @param list<int> $ids
     * @return array<int, array{id:int,active:int,deleted_at:string|null}>
     */
    public function lifecycleStates(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<array{id:int,active:int,deleted_at:string|null}> $states */
        $states = $this->query()
            ->from('admin_notification')
            ->select(['id', 'active', 'deleted_at'])
            ->whereIn('id', $ids)
            ->getArray();

        $byId = [];
        foreach ($states as $state) {
            $byId[(int) $state['id']] = $state;
        }

        return $byId;
    }

    /**
     * Zmeni aktivni stav nesmazaneho oznameni
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->query()->from('admin_notification')->where('id', $id)->where('deleted_at', null)->set(['active' => $active ? 1 : 0])->update();
    }

    /**
     * Zmeni aktivni stav pouze pri ocekavanem puvodnim stavu
     */
    public function setActiveFromState(int $id, bool $expectedActive, bool $active): bool
    {
        return $this->query()
            ->from('admin_notification')
            ->where('id', $id)
            ->where('active', $expectedActive ? 1 : 0)
            ->where('deleted_at', null)
            ->set(['active' => $active ? 1 : 0])
            ->update();
    }

    /**
     * Soft-deletne dosud nesmazane oznameni
     */
    public function softDeleteNotification(int $id): bool
    {
        return $this->query()->from('admin_notification')->where('id', $id)->where('deleted_at', null)->set(['deleted_at' => $this->now()])->update();
    }

    /**
     * Obnovi drive soft-deletnute oznameni
     */
    public function restoreNotification(int $id): bool
    {
        return $this->query()->from('admin_notification')->where('id', $id)->whereRaw('deleted_at IS NOT NULL')->set(['deleted_at' => null])->update();
    }

    /**
     * Prida roli do publika oznameni vcetne stabilniho kodu a nazvu
     *
     * @param array{id:int,code:string,name:string} $role
     */
    public function addRoleAudience(int $notificationId, array $role): void
    {
        $this->db->query('INSERT INTO admin_notification_audience_role(notification_id,role_id,role_code,role_name) VALUES (?,?,?,?)', [$notificationId, $role['id'], $role['code'], $role['name']]);
    }

    /**
     * Prida uzivatele do publika oznameni vcetne e-mailu pro presentation
     *
     * @param array{id:int,email:string} $user
     */
    public function addUserAudience(int $notificationId, array $user): void
    {
        $this->db->query('INSERT INTO admin_notification_audience_user(notification_id,user_id,user_email) VALUES (?,?,?)', [$notificationId, $user['id'], $user['email']]);
    }

    /**
     * Vytvori zaznamy zobrazeni pro konkretni prijemce oznameni
     *
     * @param list<int> $recipientIds
     */
    public function addRecipients(int $notificationId, array $recipientIds): void
    {
        foreach ($recipientIds as $recipientId) {
            $now = $this->now();
            $identity = ActorIdentity::local($recipientId);
            $this->db->query(
                'INSERT INTO admin_notification_recipient(notification_id,recipient_key,recipient_user_id,created_at,updated_at) VALUES (?,?,?,?,?)',
                [$notificationId, $identity->key(), $identity->localUserId(), $now, $now],
            );
        }
    }

    /**
     * Vrati nesmazane role odpovidajici zadanemu publiku
     *
     * @param list<int> $roleIds
     * @return list<array{id:int,code:string,name:string}>
     */
    public function activeRolesByIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        /** @var list<array{id:int,code:string,name:string}> $roles */
        $roles = $this->query()->from('system_role')->select(['id', 'code', 'name'])->whereIn('id', array_values($roleIds))->where('deleted_at', null)->orderBy('id')->getArray();
        return $roles;
    }

    /**
     * Vrati aktivni nesmazane uzivatele odpovidajici zadanemu publiku
     *
     * @param list<int> $userIds
     * @return list<array{id:int,email:string}>
     */
    public function activeUsersByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        /** @var list<array{id:int,email:string}> $users */
        $users = $this->query()->from('system_user')->select(['id', 'email'])->whereIn('id', array_values($userIds))->where('active', 1)->where('deleted_at', null)->orderBy('id')->getArray();
        return $users;
    }

    /**
     * Vrati vsechny nesmazane role pro vyber publika oznameni
     *
     * @return list<array{id:int,code:string,name:string,is_super_admin:int}>
     */
    public function allActiveRoles(): array
    {
        /** @var list<array{id:int,code:string,name:string,is_super_admin:int}> $roles */
        $roles = $this->query()->from('system_role')->select(['id', 'code', 'name', 'is_super_admin'])->where('deleted_at', null)->orderBy('name')->orderBy('id')->getArray();
        return $roles;
    }

    /**
     * Vyhleda aktivni nesmazane uzivatele pro asynchronni vyber publika
     *
     * @return list<array{id:int,email:string,first_name:string,last_name:string}>
     */
    public function searchActiveUsers(string $search, int $limit, int $offset = 0): array
    {
        $builder = $this->query()->from('system_user')->select(['id', 'email', 'first_name', 'last_name'])
            ->where('active', 1)->where('deleted_at', null);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $builder = $builder->whereRaw('(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)', [$like, $like, $like]);
        }

        /** @var list<array{id:int,email:string,first_name:string,last_name:string}> $users */
        $users = $builder->orderBy('last_name')->orderBy('first_name')->orderBy('email')->limit($limit, $offset)->getArray();

        return $users;
    }

    /**
     * Vrati filtrovana oznameni vcetne shrnuti publika pro management DataGrid
     *
     * @return QueryPage<array{id:int,type:string,title:string,active:int,deleted_at:string|null,created_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null,audience:string}>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        $total = $this->filteredDataGridQuery($query)->countAllResults();
        $rowsQuery = $this->filteredDataGridQuery($query);
        $sorts = ['createdAt' => 'n.created_at', 'title' => 'n.title', 'type' => 'n.type', 'author' => 'u.email', 'status' => 'n.active'];
        /** @var list<array{id:int,type:string,title:string,active:int,deleted_at:string|null,created_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null}> $rows */
        $rows = $rowsQuery->select(['n.id', 'n.type', 'n.title', 'n.active', 'n.deleted_at', 'n.created_at'])->selectRaw('u.first_name AS author_first_name, u.last_name AS author_last_name, u.email AS author_email')->orderBy($sorts[$query->sortKey()], $query->sortDirection() === 'asc' ? 'ASC' : 'DESC')->orderBy('n.id', 'DESC')->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())->getArray();
        $audiences = $this->audienceSummaries(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        /** @var list<array{id:int,type:string,title:string,active:int,deleted_at:string|null,created_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null,audience:string}> $data */
        $data = array_map(static fn(array $row): array => [...$row, 'audience' => $audiences[(int) $row['id']] ?? 'users:'], $rows);
        return new QueryPage($data, $query->page(), $query->pageSize(), $total);
    }

    /**
     * Postupne vraci reportni radky pro vybrana oznameni
     *
     * @param list<int> $ids
     * @return Generator<int, array{id:int,title:string,active:int,deleted_at:string|null,created_at:string,updated_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null,audience:string}>
     */
    public function iterateExportRows(array $ids, int $chunkSize = 100): Generator
    {
        foreach (array_chunk($ids, max(1, $chunkSize)) as $chunk) {
            /** @var list<array{id:int,title:string,active:int,deleted_at:string|null,created_at:string,updated_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null}> $rows */
            $rows = $this->query()
                ->from('admin_notification n')
                ->join('system_user u', 'u.id = n.created_by_user_id', 'LEFT')
                ->select(['n.id', 'n.title', 'n.active', 'n.deleted_at', 'n.created_at', 'n.updated_at'])
                ->selectRaw('u.first_name AS author_first_name, u.last_name AS author_last_name, u.email AS author_email')
                ->whereIn('n.id', $chunk)
                ->orderBy('n.id')
                ->getArray();
            $audiences = $this->audienceSummaries(array_map(static fn(array $row): int => (int) $row['id'], $rows));

            foreach ($rows as $row) {
                yield [...$row, 'audience' => $audiences[(int) $row['id']] ?? 'users:'];
            }
        }
    }

    /**
     * Sestavi filtrovany zaklad dotazu management DataGridu
     */
    private function filteredDataGridQuery(DataGridQuery $query): QueryBuilder
    {
        $builder = $this->query()
            ->from('admin_notification n')
            ->join('system_user u', 'u.id = n.created_by_user_id', 'LEFT');

        $status = $query->filter('status');
        if ($status === 'deleted') {
            $builder = $builder->whereRaw('n.deleted_at IS NOT NULL');
        } else {
            $builder = $builder->where('n.deleted_at', null);
            if ($status === 'active') {
                $builder = $builder->where('n.active', 1);
            } elseif ($status === 'inactive') {
                $builder = $builder->where('n.active', 0);
            }
        }

        if ($query->search() !== '') {
            $like = '%' . $query->search() . '%';
            $builder = $builder->whereRaw(
                '(n.title LIKE ? OR n.message LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR EXISTS (SELECT 1 FROM admin_notification_audience_role ar WHERE ar.notification_id = n.id AND (ar.role_name LIKE ? OR ar.role_code LIKE ?)) OR EXISTS (SELECT 1 FROM admin_notification_audience_user au WHERE au.notification_id = n.id AND au.user_email LIKE ?))',
                [$like, $like, $like, $like, $like, $like, $like, $like],
            );
        }

        return $builder;
    }

    /**
     * Sestavi shrnuti publika pro zadanou sadu oznameni bez N+1 dotazu
     *
     * @param list<int> $notificationIds
     * @return array<int, string>
     */
    private function audienceSummaries(array $notificationIds): array
    {
        if ($notificationIds === []) {
            return [];
        }

        $roles = [];
        /** @var list<array{notification_id:int,role_name:string}> $roleRows */
        $roleRows = $this->query()->from('admin_notification_audience_role')->select(['notification_id', 'role_name'])->whereIn('notification_id', $notificationIds)->orderBy('role_name')->getArray();
        foreach ($roleRows as $row) {
            $roles[(int) $row['notification_id']][] = (string) $row['role_name'];
        }

        $users = [];
        /** @var list<array{notification_id:int,user_email:string}> $userRows */
        $userRows = $this->query()->from('admin_notification_audience_user')->select(['notification_id', 'user_email'])->whereIn('notification_id', $notificationIds)->orderBy('user_email')->getArray();
        foreach ($userRows as $row) {
            $users[(int) $row['notification_id']][] = (string) $row['user_email'];
        }

        $audiences = [];
        foreach ($notificationIds as $notificationId) {
            $audiences[$notificationId] = isset($roles[$notificationId])
                ? 'roles:' . implode(', ', $roles[$notificationId])
                : 'users:' . implode(', ', $users[$notificationId] ?? []);
        }

        return $audiences;
    }

    /**
     * Sestavi canonical dotaz na aktivni oznameni viditelna pro prijemce
     */
    private function inboxQuery(NotificationRecipientIdentity $recipient): QueryBuilder
    {
        $receipts = $this->query()->from('admin_notification_recipient')->where('recipient_key', $recipient->key());
        $roles = $recipient->roleCodes();
        $placeholders = $roles === [] ? '' : implode(', ', array_fill(0, count($roles), '?'));
        $conditions = ['nr.recipient_key IS NOT NULL'];
        $bindings = [];
        $conditions[] = 'EXISTS (SELECT 1 FROM admin_notification_audience_user au WHERE au.notification_id = n.id AND au.user_id = ?)';
        $bindings[] = $recipient->localUserId();
        if ($placeholders !== '') {
            $conditions[] = 'EXISTS (SELECT 1 FROM admin_notification_audience_role ar WHERE ar.notification_id = n.id AND ar.role_code IN (' . $placeholders . '))';
            array_push($bindings, ...$roles);
        }
        return $this->query()->from('admin_notification n')->joinSubquery($receipts, 'nr', 'nr.notification_id = n.id', 'LEFT')->join('system_user u', 'u.id = n.created_by_user_id', 'LEFT')->where('n.active', 1)->where('n.deleted_at', null)->whereRaw('(' . implode(' OR ', $conditions) . ')', $bindings);
    }
}
