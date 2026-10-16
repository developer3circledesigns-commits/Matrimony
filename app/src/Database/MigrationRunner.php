<?php
declare(strict_types=1);

namespace Matrimony\Database;

/**
 * Idempotent migration runner shared by CLI (migrate.php) and the
 * key-gated web endpoint (/migrate?key=...) for shared hosting without SSH.
 * Safe to run multiple times; preserves existing data.
 */
final class MigrationRunner
{
    /** @return array{ok:bool,lines:string[]} */
    public static function run(): array
    {
        $lines = [];
        $log = function (string $s) use (&$lines): void {
            $lines[] = $s;
        };
        try {
            $pdo = Connection::pdo();
        } catch (\Throwable $e) {
            $log('DB connection failed: ' . $e->getMessage());
            return ['ok' => false, 'lines' => $lines];
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(80) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            $log('Could not create schema_migrations: ' . $e->getMessage());
            return ['ok' => false, 'lines' => $lines];
        }

        // Ensure new users columns exist even before 008 runs (fresh ordering safety)
        foreach ([
            "ALTER TABLE users ADD COLUMN role ENUM('user','moderator','admin','super_admin') NOT NULL DEFAULT 'user'",
            "ALTER TABLE users ADD COLUMN account_status ENUM('pending_verification','active','suspended','deactivated','deleted') NOT NULL DEFAULT 'active'",
            'ALTER TABLE users ADD COLUMN email_normalized VARCHAR(190) NULL',
            'ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL',
            'ALTER TABLE users ADD COLUMN phone_normalized VARCHAR(20) NULL',
            'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL',
            'ALTER TABLE users ADD COLUMN phone_verified_at DATETIME NULL',
            'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL',
            'ALTER TABLE users ADD COLUMN must_reauth_at DATETIME NULL',
        ] as $ddl) {
            try {
                $pdo->exec($ddl);
            } catch (\Throwable $e) {
                // tolerate duplicate column
            }
        }
        try {
            $pdo->exec('UPDATE IGNORE users SET email_normalized = LOWER(TRIM(email)) WHERE email_normalized IS NULL');
        } catch (\Throwable $e) {
        }

        $migrations = glob(BASE_PATH . '/app/database/migrations/*.sql') ?: [];
        sort($migrations);

        foreach ($migrations as $file) {
            $version = basename($file);
            try {
                $s = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :v');
                $s->execute([':v' => $version]);
                if ($s->fetchColumn()) {
                    $log("SKIP $version (already applied)");
                    continue;
                }
            } catch (\Throwable $e) {
            }
            $log("APPLY $version ...");
            $sql = (string) file_get_contents($file);
            $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
            $ok = true;
            foreach ($statements as $stmt) {
                $code = preg_replace('/^\s*--.*$/m', '', $stmt);
                if (trim((string) $code) === '') {
                    continue;
                }
                // ADD COLUMN IF NOT EXISTS is MariaDB-only; the .sql files use
                // plain ADD COLUMN (phpMyAdmin-safe). Skip already-added columns
                // here so web/CLI re-runs stay idempotent.
                if (preg_match('/ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?/i', $stmt, $m)) {
                    if (self::columnExists($pdo, $m[1], $m[2])) {
                        continue;
                    }
                    $stmt = (string) preg_replace('/ADD COLUMN IF NOT EXISTS/i', 'ADD COLUMN', $stmt);
                }
                try {
                    $pdo->exec($stmt);
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'Duplicate key') !== false || stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate entry') !== false) {
                        $log('  (tolerated) ' . substr($msg, 0, 160));
                        continue;
                    }
                    $log('  FAILED: ' . substr($msg, 0, 500));
                    $log('  SQL: ' . substr($stmt, 0, 300));
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                try {
                    $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (:v)')->execute([':v' => $version]);
                } catch (\Throwable $e) {
                }
                $log("DONE $version");
            } else {
                $log("ABORTED $version — fix error and re-run");
                return ['ok' => false, 'lines' => $lines];
            }
        }

        // Post-migration indexes
        try {
            $dups = $pdo->query("SELECT LOWER(TRIM(email)) e, COUNT(*) c FROM users GROUP BY e HAVING c > 1 LIMIT 5")->fetchAll();
            if ($dups) {
                $log('WARNING: duplicate emails exist; unique index on email_normalized skipped. Resolve manually.');
            } else {
                $has = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_email_norm'")->fetch();
                if (!$has) {
                    $pdo->exec('CREATE UNIQUE INDEX uq_users_email_norm ON users (email_normalized)');
                    $log('Added uq_users_email_norm');
                }
            }
        } catch (\Throwable $e) {
            $log('Index note: ' . $e->getMessage());
        }
        try {
            $has = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_role_status'")->fetch();
            if (!$has) {
                $pdo->exec('CREATE INDEX idx_users_role_status ON users (role, account_status)');
                $log('Added idx_users_role_status');
            }
            $has = $pdo->query("SHOW INDEX FROM memberships WHERE Key_name = 'idx_memberships_user_status'")->fetch();
            if (!$has) {
                $pdo->exec('CREATE INDEX idx_memberships_user_status ON memberships (user_id, status, ends_at)');
                $log('Added idx_memberships_user_status');
            }
            $has = $pdo->query("SHOW INDEX FROM membership_plans WHERE Key_name = 'uq_plans_slug'")->fetch();
            if (!$has) {
                try {
                    $pdo->exec('CREATE UNIQUE INDEX uq_plans_slug ON membership_plans (slug)');
                    $log('Added uq_plans_slug');
                } catch (\Throwable $e) {
                    $log('Slug index skipped: ' . substr($e->getMessage(), 0, 120));
                }
            }
        } catch (\Throwable $e) {
            $log('Index note: ' . $e->getMessage());
        }

        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
            $log("Super admins: $count");
            if ($count === 0) {
                $log('NOTE: no super_admin yet. Use /admin/admins (as super_admin) or CLI create-admin.php.');
            }
        } catch (\Throwable $e) {
            $log('Super-admin check skipped: ' . substr($e->getMessage(), 0, 120));
        }
        Schema::clearCache();
        $log('All migrations complete.');
        return ['ok' => true, 'lines' => $lines];
    }

    private static function columnExists(\PDO $pdo, string $table, string $col): bool
    {
        try {
            $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
            $s->execute([':t' => $table, ':c' => $col]);
            return (bool) $s->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
