<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

use Lemonade\Framework\Database\Database;

/**
 * Poskytuje existujici lokalni uzivatele pro bezpecne prirazeni entity
 */
final readonly class LocalUserDirectory
{
    /**
     * Nastavuje canonical uloziste lokalnich uzivatelu
     */
    public function __construct(private Database $database) {}

    /**
     * Vrati nesmazane uzivatele serazene pro vyber vlastnika
     *
     * @return list<array{id:int,label:string}>
     */
    public function assignableUsers(): array
    {
        $users = [];
        foreach ($this->database->select(
            'SELECT id, first_name, last_name, email FROM system_user WHERE deleted_at IS NULL ORDER BY last_name, first_name, email, id',
        ) as $row) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $label = $name === '' ? $email : ($email === '' ? $name : $name . ' (' . $email . ')');
            $users[] = [
                'id' => (int) $row['id'],
                'label' => $label === '' ? '#' . (int) $row['id'] : $label,
            ];
        }

        return $users;
    }

    /**
     * Overi existenci nesmazaneho lokalniho uzivatele
     */
    public function exists(int $userId): bool
    {
        return $userId > 0 && $this->database->select(
            'SELECT 1 FROM system_user WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$userId],
        ) !== [];
    }
}
