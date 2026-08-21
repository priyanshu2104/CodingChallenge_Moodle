<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Provides a configured PDO connection to PostgreSQL.
 * Connection details are read from environment variables (via .env).
 */
final class Connection
{
    private static ?PDO $instance = null;

    /**
     * Returns a singleton PDO connection.
     *
     * @throws RuntimeException if the connection cannot be established.
     */
    public static function get(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Resets the singleton — useful in tests.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function createConnection(): PDO
    {
        $host = self::requireEnv('DB_HOST');
        $port = self::requireEnv('DB_PORT');
        $name = self::requireEnv('DB_NAME');
        $user = self::requireEnv('DB_USER');
        $pass = self::requireEnv('DB_PASS');

        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Database connection failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }

        return $pdo;
    }

    private static function requireEnv(string $key): string
    {
        // phpdotenv createImmutable() populates $_ENV / $_SERVER but not getenv(),
        // so we check all three sources in priority order.
        // Note: DB_PASS may intentionally be an empty string (passwordless local auth).
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        throw new RuntimeException("Missing required environment variable: {$key}");
    }
}
