<?php
declare(strict_types=1);
// Shared-hosting cron entrypoint. Run every 5-15 min:
//   php /path/to/app/cron/cron.php
// Or via Hostinger cron: php /home/uXXXX/public_html/app/cron/cron.php
// Web access is disabled unless a non-empty CRON_KEY is configured AND the
// supplied key matches (constant-time). CLI always runs.
$cronKey = (string) (getenv('CRON_KEY') ?: '');
if (PHP_SAPI !== 'cli') {
    // Prefer X-Cron-Key header (avoids query-string leakage in logs/Referer).
    $provided = (string) ($_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if ($provided === '') {
        $provided = (string) ($_GET['key'] ?? '');
    }
    if ($cronKey === '' || $provided === '' || !hash_equals($cronKey, $provided)) {
        http_response_code(403);
        exit($cronKey === '' ? 'CLI only (set CRON_KEY to enable web cron)' : 'Forbidden');
    }
}
require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Services\EmailOutboxService;
use Matrimony\Services\MembershipService;

// 1. Retry queued emails (never blocks payments)
[$sent, $failed] = EmailOutboxService::processDue(25);
echo "outbox: sent=$sent failed=$failed\n";

// 2. Housekeeping: mark expired memberships for reporting (entitlement never depends on this)
$n = MembershipService::markExpired();
echo "expired_marked=$n\n";

// 3. Cleanup old rate-limit rows
try {
    $pdo = \Matrimony\Database\Connection::pdo();
    $pdo->exec("DELETE FROM rate_limits WHERE updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
    echo "ratelimit_cleanup=ok\n";
} catch (\Throwable $e) {
    echo 'ratelimit_cleanup=skip' . "\n";
}
echo "done\n";
