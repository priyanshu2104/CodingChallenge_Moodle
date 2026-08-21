<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Manages the users table DDL.
 */
final class Schema
{
    /**
     * Creates (or recreates) the users table.
     *
     * When $drop is true the existing table is dropped first, which lets
     * the --create-table CLI flag rebuild a clean schema.
     */
    public static function createUsersTable(PDO $pdo, bool $drop = false): void
    {
        if ($drop) {
            $pdo->exec('DROP TABLE IF EXISTS users');
        }

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                surname    VARCHAR(255) NOT NULL,
                email      VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP    NOT NULL DEFAULT NOW()
            )
        SQL);
    }
}
