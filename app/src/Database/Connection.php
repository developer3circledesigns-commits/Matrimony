<?php
namespace Matrimony\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;
    private static array $configCache = [];

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::getConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        // Payments, memberships and the email outbox use UTC_TIMESTAMP()
        // everywhere. Pin the connection timezone so DEFAULT CURRENT_TIMESTAMP
        // cannot drift between hosting environments. A restricted host may
        // disallow SET; that must not take the whole app offline.
        try {
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            @error_log('Connection: could not pin the MySQL session timezone to UTC.');
        }

        return self::$pdo;
    }

    public static function reconnect(): void
    {
        self::$pdo = null;
        self::pdo();
    }

    private static function getConfig(): array
    {
        if (!empty(self::$configCache)) {
            return self::$configCache;
        }
        self::$configCache = require BASE_PATH . '/app/config/database.php';
        return self::$configCache;
    }
}
