<?php
declare(strict_types=1);

namespace Tests;

use PDO;
use PDOException;
use RuntimeException;

final class TestDatabase
{
    private static ?PDO $pdo = null;
    private static string $testDb = 'matrimony_test';
    private static bool $schemaLoaded = false;

    /**
     * Get a PDO connection to the test database.
     * Creates the test DB and runs migrations if needed.
     */
    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';
        $dbName = getenv('DB_DATABASE') ?: 'matrimony_test';

        try {
            // Connect without DB first to create it if needed
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            self::$pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            if (!self::$schemaLoaded) {
                self::runMigrations(self::$pdo);
                self::$schemaLoaded = true;
            }
            return self::$pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Test database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Reset all tables (truncate) for a fresh test state.
     */
    public static function reset(): void
    {
        $pdo = self::connect();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        }
    }

    /**
     * Drop and recreate the test database to pick up schema changes.
     */
    public static function recreate(): void
    {
        self::$pdo = null;
        self::$schemaLoaded = false;

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("DROP DATABASE IF EXISTS `matrimony_test`");
    }

    /**
     * Load fixtures from the fixtures SQL file.
     */
    public static function loadFixtures(): void
    {
        $pdo = self::connect();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $fixturesFile = BASE_PATH . '/tests/fixtures/test_data.sql';
        if (!is_file($fixturesFile)) {
            throw new RuntimeException("Fixtures file not found: {$fixturesFile}");
        }
        $sql = file_get_contents($fixturesFile);
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Skip data truncation warnings (strict mode elevates them to errors)
                    if (str_contains($e->getMessage(), 'Data truncated')) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Inject the test PDO into the application's Connection class.
     */
    public static function injectPdo(): void
    {
        $pdo = self::connect();
        $ref = new \ReflectionProperty(\Matrimony\Database\Connection::class, 'pdo');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdo);
    }

    private static function runMigrations(PDO $pdo): void
    {
        $schemaFile = BASE_PATH . '/database/matrimony_complete.sql';
        if (!is_file($schemaFile)) {
            throw new RuntimeException("Schema file not found: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate key name')) {
                        continue;
                    }
                    throw $e;
                }
            }
        }
    }

    /**
     * Get the demo user ID from fixtures (assumes user 1 is the demo user).
     */
    public static function demoUserId(): int
    {
        return 1;
    }

    /**
     * Get a second user ID for testing interactions.
     */
    public static function secondUserId(): int
    {
        return 2;
    }
}
