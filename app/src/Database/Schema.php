<?php
declare(strict_types=1);

namespace Matrimony\Database;

/**
 * Schema-compat helper. Production databases may not have migration 008
 * applied yet; callers use this to degrade gracefully instead of fataling.
 */
final class Schema
{
    /** @var array<string,array|null> table => columns (null = unknown/error) */
    private static array $cache = [];

    /** @return string[] lowercase column names, [] if table missing/unreadable */
    public static function columns(string $table): array
    {
        if (array_key_exists($table, self::$cache)) {
            return self::$cache[$table] ?? [];
        }
        try {
            $pdo = Connection::pdo();
            // Works on MySQL + MariaDB without information_schema privileges issues
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $cols = [];
            foreach ($stmt->fetchAll() as $row) {
                $cols[] = strtolower((string) ($row['Field'] ?? ''));
            }
            self::$cache[$table] = $cols;
            return $cols;
        } catch (\Throwable $e) {
            self::$cache[$table] = [];
            return [];
        }
    }

    public static function has(string $table, string $column): bool
    {
        return in_array(strtolower($column), self::columns($table), true);
    }

    public static function tableExists(string $table): bool
    {
        return self::columns($table) !== [];
    }

    /** True when migration 008 (plans/payments schema) is fully applied. */
    public static function paymentsReady(): bool
    {
        return self::has('membership_plans', 'slug')
            && self::has('membership_plans', 'price_paise')
            && self::tableExists('purchases')
            && self::tableExists('activation_requests')
            && self::tableExists('contact_reveals');
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
