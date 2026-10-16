<?php
declare(strict_types=1);

/**
 * Key-gated migration + diagnostics endpoint for shared hosting without SSH.
 * Prefer header: X-Cron-Key. Query-string key still accepted for one-time ops.
 * Disable after go-live by unsetting CRON_KEY or setting MIGRATE_DISABLED=1.
 */
use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Database\Connection;
use Matrimony\Database\Schema;
use Matrimony\Database\MigrationRunner;

final class MigrateController extends Controller
{
    public function __invoke(Request $request): void
    {
        if (filter_var(getenv('MIGRATE_DISABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not found.\n";
            return;
        }

        $expected = (string) (getenv('CRON_KEY') ?: '');
        $key = (string) ($_SERVER['HTTP_X_CRON_KEY'] ?? $_POST['key'] ?? $_GET['key'] ?? '');
        if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Forbidden.\nProvide CRON_KEY via X-Cron-Key header (preferred) or form field.\n";
            return;
        }

        $wantRun = $request::is('POST');
        if ($wantRun) {
            // CRON_KEY is the auth gate (Csrf::require exits — unsuitable for curl/ops).
            $result = MigrationRunner::run();
            $this->page('Migration result', $this->renderResult($result) . $this->renderStatus());
            return;
        }
        $this->page('Migration status', $this->renderStatus(true));
    }

    /** @return array<int,array{label:string,ok:bool,detail:string}> */
    private function checks(): array
    {
        $out = [];
        try {
            $pdo = Connection::pdo();
            $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            // Do not leak DB host on status page.
            $out[] = ['label' => 'DB connection', 'ok' => true, 'detail' => 'connected (' . $db . ')'];
        } catch (\Throwable $e) {
            $out[] = ['label' => 'DB connection', 'ok' => false, 'detail' => 'unavailable'];
            return $out;
        }
        $cols = [
            ['users', 'role'], ['users', 'account_status'], ['users', 'email_normalized'],
            ['membership_plans', 'slug'], ['membership_plans', 'price_paise'],
            ['membership_plans', 'contact_limit'], ['membership_plans', 'is_unlimited'],
            ['memberships', 'purchase_id'],
        ];
        foreach ($cols as [$t, $c]) {
            $ok = Schema::has($t, $c);
            $out[] = ['label' => "column $t.$c", 'ok' => $ok, 'detail' => $ok ? 'present' : 'MISSING'];
        }
        foreach (['purchases', 'gateway_webhook_events', 'activation_requests', 'contact_reveals', 'notifications', 'audit_logs', 'email_outbox', 'refunds', 'password_resets', 'schema_migrations', 'rate_limits'] as $t) {
            $ok = Schema::tableExists($t);
            $out[] = ['label' => "table $t", 'ok' => $ok, 'detail' => $ok ? 'present' : 'MISSING'];
        }
        try {
            $pdo = Connection::pdo();
            $n = (int) $pdo->query("SELECT COUNT(*) FROM membership_plans WHERE code IN ('SILVER','GOLD','PLATINUM')")->fetchColumn();
            $out[] = ['label' => 'poster plans seeded', 'ok' => $n === 3, 'detail' => "$n/3 present"];
        } catch (\Throwable $e) {
            $out[] = ['label' => 'poster plans seeded', 'ok' => false, 'detail' => 'query failed'];
        }
        try {
            $pdo = Connection::pdo();
            $n = Schema::has('users', 'role') ? (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn() : 0;
            $out[] = ['label' => 'super_admin exists', 'ok' => $n > 0, 'detail' => $n > 0 ? 'yes' : 'none — create one'];
        } catch (\Throwable $e) {
            $out[] = ['label' => 'super_admin exists', 'ok' => false, 'detail' => 'query failed'];
        }
        $out[] = ['label' => 'overall paymentsReady()', 'ok' => Schema::paymentsReady(), 'detail' => Schema::paymentsReady() ? 'YES' : 'NO — run migrations'];
        return $out;
    }

    private function renderStatus(bool $withRun = false): string
    {
        $h = '<h2>Status checks</h2><table border="1" cellpadding="6" cellspacing="0"><tr><th>Check</th><th>Result</th><th>Detail</th></tr>';
        foreach ($this->checks() as $c) {
            $h .= '<tr><td>' . htmlspecialchars($c['label']) . '</td><td>' . ($c['ok'] ? 'OK' : 'FAIL') . '</td><td>' . htmlspecialchars($c['detail']) . '</td></tr>';
        }
        $h .= '</table>';
        if ($withRun) {
            $h .= '<form method="post" action="/migrate">'
                . '<input type="hidden" name="key" value="' . htmlspecialchars((string) ($_SERVER['HTTP_X_CRON_KEY'] ?? $_GET['key'] ?? '')) . '">'
                . '<button type="submit">Run migrations now</button></form>';
            $h .= '<p><small>Idempotent. After success set MIGRATE_DISABLED=1 and rotate CRON_KEY.</small></p>';
        }
        return $h;
    }

    private function renderResult(array $result): string
    {
        $h = '<h2>' . ($result['ok'] ? 'Migration OK' : 'Migration FAILED') . '</h2><pre>';
        foreach ($result['lines'] as $line) {
            $h .= htmlspecialchars($line) . "\n";
        }
        return $h . '</pre><p><a href="/migrate">Back to status</a> (send X-Cron-Key header)</p>';
    }

    private function page(string $title, string $body): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
        echo '<style>body{font-family:sans-serif;max-width:900px;margin:2em auto;padding:0 1em}pre{background:#f4f4f4;padding:1em;overflow:auto}</style>';
        echo '</head><body><h1>' . htmlspecialchars($title) . '</h1>' . $body . '</body></html>';
    }
}

(new MigrateController())(new Request());
