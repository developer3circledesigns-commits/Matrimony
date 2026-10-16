<?php
declare(strict_types=1);

/**
 * Idempotent migration runner.
 * Usage (CLI): php app/database/migrate.php
 * Safe to run multiple times; preserves existing data.
 * No SSH? Use the key-gated web endpoint: /migrate?key=<CRON_KEY>
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Database\MigrationRunner;

$result = MigrationRunner::run();
foreach ($result['lines'] as $line) {
    echo $line . "\n";
}
exit($result['ok'] ? 0 : 1);
