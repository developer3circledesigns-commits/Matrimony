<?php
declare(strict_types=1);
require __DIR__ . '/../app/includes/bootstrap.php';
if (PHP_SAPI !== 'cli'
    && ((getenv('APP_ENV') ?: 'production') === 'production'
        || !filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN))) {
    http_response_code(404);
    exit('Not found');
}
use Matrimony\Database\Connection;
use Matrimony\Database\Schema;
$pdo = Connection::pdo();
echo "DB: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "paymentsReady: " . (Schema::paymentsReady() ? 'YES' : 'NO') . "\n\n";
echo "Tables:\n";
foreach ($pdo->query("SHOW TABLES") as $r) { echo "  " . reset($r) . "\n"; }
echo "\nschema_migrations:\n";
try {
    foreach ($pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY applied_at, version") as $r) {
        echo "  " . ($r['version'] ?? '') . " @ " . ($r['applied_at'] ?? '') . "\n";
    }
} catch (Throwable $e) { echo "  (none: " . $e->getMessage() . ")\n"; }
echo "\nmembership_plans:\n";
try {
    foreach ($pdo->query("SELECT id,code,name,price_paise,duration_days,contact_limit,is_unlimited,is_active FROM membership_plans ORDER BY display_order") as $r) {
        echo "  " . json_encode($r) . "\n";
    }
} catch (Throwable $e) { echo "  (none: " . $e->getMessage() . ")\n"; }
echo "\nusers count: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
try { echo "roles: " . json_encode($pdo->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR)) . "\n"; } catch (Throwable $e) {}
