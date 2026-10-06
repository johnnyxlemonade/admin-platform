<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

use Lemonade\Framework\Database\Connection\DatabaseConfig;

/**
 * Overuje nastaveni databaze pred instalaci
 */
final class DatabaseConfigurationValidator
{
    public function errorCode(DatabaseConfig $config): ?string
    {
        if (trim($config->host()) === '') {
            return 'host';
        }
        if ($config->port() < 1 || $config->port() > 65535) {
            return 'port';
        }
        if (trim($config->database()) === '') {
            return 'name';
        }
        if (trim($config->username()) === '') {
            return 'user';
        }

        return null;
    }

    public function isValid(DatabaseConfig $config): bool
    {
        return $this->errorCode($config) === null;
    }
}
