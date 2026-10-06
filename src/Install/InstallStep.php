<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

/**
 * Vymezuje kroky instalace aplikace
 */
enum InstallStep: string
{
    case Discovery = 'discovery';
    case Database = 'database';
    case Modules = 'modules';
    case Permissions = 'permissions';
    case Root = 'root';

    /**
     * Vraci serazene kroky cele instalace
     * @return list<self>
     */
    public static function pipeline(): array
    {
        return [self::Discovery, self::Database, self::Modules, self::Permissions, self::Root];
    }

    /**
     * Vraci nasledujici krok instalacni posloupnosti
     */
    public function next(): ?self
    {
        $pipeline = self::pipeline();
        $index = array_search($this, $pipeline, true);

        return is_int($index) ? ($pipeline[$index + 1] ?? null) : null;
    }
}
