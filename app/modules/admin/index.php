<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Authorization;
use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;
use Matrimony\Security\RateLimiter;

final class AdminController extends Controller
{
    public function __invoke(Request $request): void
    {
        $segments = array_values(array_filter(explode('/', trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/'))));
        $page = $segments[1] ?? 'dashboard';

        if ($page === 'login') {
            $this->handleLogin($request);
            return;
        }
        if ($page === 'reauth') {
            $this->handleReauth($request);
            return;
        }

        $admin = Authorization::requireAdmin();
        $isSuper = $admin['role'] === 'super_admin';

        switch ($page) {
            case 'dashboard':
                $this->dashboard($admin);
                break;
            case 'users':
                $this->users($admin, $segments[2] ?? null, $segments[3] ?? null);
                break;
            case 'profiles':
                $this->profiles($admin);
                break;
            case 'plans':
                $this->plans($admin, $isSuper);
                break;
            case 'payments':
                Authorization::requireReauth(3600); // view allowed w/ normal session; actions re-check
                $this->payments($admin, $isSuper);
                break;
            case 'reports':
                $this->reports($admin);
                break;
            case 'audit':
                $this->audit($admin, $isSuper);
                break;
            case 'admins':
                if (!$isSuper) {
                    http_response_code(403);
                    echo 'Forbidden';
                    return;
                }
                $this->admins($admin);
                break;
            case 'outbox':
                $this->outbox($admin);
                break;
            default:
                http_response_code(404);
                $this->renderAdmin($admin, 'Not found', $this->errorPanel('This admin page does not exist.'), [
                    'subtitle' => 'Check the address or head back to the dashboard.',
                    'actions' => '<a class="btn btn-primary btn-sm" href="/admin/dashboard">Back to dashboard</a>',
                ]);
        }
    }

    // ---------- login / reauth ----------
    private function handleLogin(Request $request): void
    {
        if (Auth::id()) {
            $u = Authorization::currentUser();
            if ($u && in_array($u['role'], ['admin', 'super_admin'], true)) {
                $this->redirect('/admin/dashboard');
                return;
            }
        }
        $error = null;
        $email = '';
        if ($request::is('POST')) {
            Csrf::require();
            if (!RateLimiter::attempt(RateLimiter::keyFor('admin-login'), 10, 600)) {
                $error = 'Too many attempts. Try again later.';
            } else {
                $email = strtolower(trim((string) $request::input('email', '')));
                $password = (string) $request::input('password', '');
                $pdo = Connection::pdo();
                try {
                    if (\Matrimony\Database\Schema::has('users', 'role')) {
                        $stmt = $pdo->prepare("SELECT id, password_hash, role, account_status FROM users WHERE email_normalized = :e OR email = :e2 LIMIT 1");
                        $stmt->execute([':e' => $email, ':e2' => $email]);
                    } else {
                        // Legacy schema fallback (migration pending) — no admin roles exist yet
                        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :e LIMIT 1");
                        $stmt->execute([':e' => $request::input('email', '')]);
                    }
                    $user = $stmt->fetch();
                } catch (\Throwable $e) {
                    $user = false;
                }
                if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
                    $error = 'Invalid credentials.';
                    AuditService::log(null, 'admin.login_failed', 'user', null, ['email' => $email], 'failure');
                } elseif (!in_array($user['role'] ?? null, ['admin', 'super_admin'], true)) {
                    $error = 'Invalid credentials.';
                } elseif (($user['account_status'] ?? 'active') !== 'active') {
                    $error = 'Account is not active.';
                } else {
                    Auth::login((int) $user['id']);
                    Authorization::markReauth();
                    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $user['id']]);
                    AuditService::log((int) $user['id'], 'admin.login', 'user', $user['id'], []);
                    flash('success', 'Welcome back.');
                    $this->redirect('/admin/dashboard');
                    return;
                }
            }
        }
        $this->view('admin/views/login', [
            'pageTitle' => 'Admin login',
            'error' => $error,
            'email' => $email,
            'csrfToken' => Csrf::token(),
        ], 'admin_auth');
    }

    private function handleReauth(Request $request): void
    {
        $admin = Authorization::requireAdmin();
        $error = null;
        $rawNext = (string) ($_POST['next'] ?? $_GET['next'] ?? '/admin/dashboard');
        $next = \Matrimony\Security\Validation::returnPath($rawNext, '/admin/dashboard');
        if ($request::is('POST')) {
            Csrf::require();
            $password = (string) $request::input('password', '');
            $pdo = Connection::pdo();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $admin['id']]);
            $hash = (string) $stmt->fetchColumn();
            if ($hash && password_verify($password, $hash)) {
                Authorization::markReauth();
                AuditService::log((int) $admin['id'], 'admin.reauth', 'user', $admin['id'], []);
                $this->redirect($next);
                return;
            }
            $error = 'Incorrect password.';
        }
        $this->view('admin/views/reauth', [
            'pageTitle' => 'Confirm identity',
            'error' => $error,
            'next' => $next,
            'csrfToken' => Csrf::token(),
        ], 'admin_auth');
    }

    // ---------- pages ----------
    private function dashboard(array $admin): void
    {
        $pdo = Connection::pdo();
        $isSuper = $admin['role'] === 'super_admin';

        // Each metric is isolated: a missing table degrades one tile instead of
        // blanking the entire dashboard.
        $stats = [];
        $stats['total_users'] = $this->count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'user'");
        $stats['active_users'] = $this->count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'user' AND account_status = 'active'");
        $stats['suspended'] = $this->count($pdo, "SELECT COUNT(*) FROM users WHERE account_status IN ('suspended','deactivated')");
        $stats['new_users_7d'] = $this->count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)");
        $stats['active_memberships'] = $this->count($pdo, "SELECT COUNT(*) FROM memberships WHERE status = 'active' AND ends_at > UTC_TIMESTAMP()");
        $stats['expiring_memberships'] = $this->count($pdo, "SELECT COUNT(*) FROM memberships WHERE status = 'active' AND ends_at > UTC_TIMESTAMP() AND ends_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)");
        $stats['awaiting_activation'] = $this->count($pdo, "SELECT COUNT(*) FROM activation_requests WHERE status = 'awaiting_admin'");
        $stats['needs_review'] = $this->count($pdo, "SELECT COUNT(*) FROM purchases WHERE payment_status = 'needs_review'");
        $stats['failed_payments'] = $this->count($pdo, "SELECT COUNT(*) FROM purchases WHERE payment_status = 'failed'");
        $stats['refund_requests'] = $this->count($pdo, "SELECT COUNT(*) FROM refunds WHERE status = 'requested'");
        $stats['disputed'] = $this->count($pdo, "SELECT COUNT(*) FROM purchases WHERE payment_status = 'disputed'");
        $stats['paid_today'] = $this->count($pdo, "SELECT COUNT(*) FROM purchases WHERE payment_status = 'paid_verified' AND paid_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
        $stats['revenue_30d_paise'] = (int) ($this->count($pdo, "SELECT COALESCE(SUM(amount_paise),0) FROM purchases WHERE payment_status = 'paid_verified' AND paid_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)"));
        $stats['open_reports'] = $this->count($pdo, "SELECT COUNT(*) FROM profile_reports WHERE status = 'open'");
        $stats['failed_webhooks'] = $this->count($pdo, "SELECT COUNT(*) FROM gateway_webhook_events WHERE processing_status = 'failed'");
        $stats['outbox_backlog'] = $this->count($pdo, "SELECT COUNT(*) FROM email_outbox WHERE status IN ('queued','sending','failed')");

        if (\Matrimony\Database\Schema::has('profiles', 'moderation_status')) {
            $stats['profiles_pending'] = $this->count($pdo, "SELECT COUNT(*) FROM profiles WHERE moderation_status = 'pending'");
            $stats['profiles_total'] = $this->count($pdo, "SELECT COUNT(*) FROM profiles");
        } else {
            $stats['profiles_pending'] = 0;
            $stats['profiles_total'] = 0;
        }

        try {
            $stats['by_plan'] = $pdo->query(
                "SELECT p.code, p.name, COUNT(m.id) c FROM membership_plans p
                 LEFT JOIN memberships m ON m.plan_id = p.id AND m.status = 'active' AND m.ends_at > UTC_TIMESTAMP()
                 WHERE p.code IN ('FREE','SILVER','GOLD','PLATINUM')
                 GROUP BY p.id ORDER BY p.display_order"
            )->fetchAll();
        } catch (\Throwable $e) {
            $stats['by_plan'] = [];
        }

        try {
            $stats['queue'] = $pdo->query(
                "SELECT ar.*, u.email, p.plan_name_snapshot, p.amount_paise
                 FROM activation_requests ar
                 JOIN purchases p ON p.id = ar.purchase_id
                 JOIN users u ON u.id = ar.user_id
                 WHERE ar.status = 'awaiting_admin'
                 ORDER BY ar.requested_at ASC LIMIT 10"
            )->fetchAll();
        } catch (\Throwable $e) {
            $stats['queue'] = [];
        }

        try {
            $limit = $isSuper ? 15 : 8;
            $stats['recent_audit'] = $pdo->query(
                'SELECT a.*, u.email FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id
                 ORDER BY a.id DESC LIMIT ' . $limit
            )->fetchAll();
        } catch (\Throwable $e) {
            $stats['recent_audit'] = [];
        }

        $body = $this->partial('admin/views/dashboard', ['stats' => $stats, 'admin' => $admin, 'csrf' => Csrf::token()]);
        $this->renderAdmin($admin, 'Dashboard', $body, [
            'subtitle' => 'Operational overview across users, memberships and payments.',
        ]);
    }

    private function users(array $admin, ?string $sub, ?string $action = null): void
    {
        try {
            $pdo = Connection::pdo();
            $isSuper = $admin['role'] === 'super_admin';

            // /admin/users/create
            if ($sub === 'create') {
                $this->userForm($admin, null, $isSuper);
                return;
            }
            // /admin/users/{id} and /admin/users/{id}/edit
            if ($sub !== null && is_numeric($sub)) {
                $id = (int) $sub;
                if ($action === 'edit') {
                    $this->userForm($admin, $id, $isSuper);
                    return;
                }
                $this->userDetail($admin, $pdo, $id);
                return;
            }
            if ($sub !== null) {
                $this->renderAdmin($admin, 'Not found', $this->errorPanel('Unknown users route.'), [
                    'breadcrumb' => ['Users' => null],
                ]);
                return;
            }

            $f = [
                'q' => trim((string) ($_GET['q'] ?? '')),
                'role' => trim((string) ($_GET['role'] ?? '')),
                'status' => trim((string) ($_GET['status'] ?? '')),
                'verified' => trim((string) ($_GET['verified'] ?? '')),
                'has_profile' => trim((string) ($_GET['has_profile'] ?? '')),
                'page' => max(1, (int) ($_GET['page'] ?? 1)),
                'sort' => (string) ($_GET['sort'] ?? 'newest'),
                'per' => (int) ($_GET['per'] ?? 25),
            ];
            $list = \Matrimony\Services\AdminUserService::listUsers($f);
            $body = $this->partial('admin/views/users', [
                'list' => $list,
                'f' => $f,
                'counts' => \Matrimony\Services\AdminUserService::statusCounts(),
                'isSuper' => $isSuper,
                'csrf' => Csrf::token(),
            ]);
            $this->renderAdmin($admin, 'Users', $body, [
                'breadcrumb' => ['Users' => null],
                'subtitle' => number_format((int) ($list['total'] ?? 0)) . ' account' . ((int) ($list['total'] ?? 0) === 1 ? '' : 's') . ' match your filters.',
                'actions' => '<a class="btn btn-primary btn-sm" href="/admin/users/create">'
                    . admin_icon('plus', 14) . ' Create user</a>',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Users', $this->errorPanel('Could not load users.', $this->safeError($e)), [
                'breadcrumb' => ['Users' => null],
            ]);
        }
    }

    /**
     * Shared renderer for the create and edit forms — they differ only in
     * whether a subject exists.
     */
    private function userForm(array $admin, ?int $id, bool $isSuper): void
    {
        $isEdit = $id !== null;
        $user = null;

        if ($isEdit) {
            $user = \Matrimony\Services\AdminUserService::find($id);
            if (!$user) {
                $this->renderAdmin($admin, 'User', $this->errorPanel('User not found.', 'No account exists with ID ' . $id . '.'), [
                    'breadcrumb' => ['Users' => '/admin/users', 'Not found' => null],
                ]);
                return;
            }
            if (!$isSuper && in_array((string) $user['role'], \Matrimony\Services\AdminUserService::PRIVILEGED_ROLES, true)) {
                $this->renderAdmin($admin, 'Edit user', $this->errorPanel(
                    'You cannot edit a privileged account.',
                    'Ask a Super Admin to change user #' . $id . '.'
                ), [
                    'breadcrumb' => ['Users' => '/admin/users', 'Edit' => null],
                ]);
                return;
            }
        }

        $form = flash_form_pull();
        $body = $this->partial($isEdit ? 'admin/views/user_edit' : 'admin/views/user_create', [
            'u' => $user,
            'isEdit' => $isEdit,
            'isSuper' => $isSuper,
            'errors' => $form['errors'],
            'old' => $form['old'],
            'admin' => $admin,
            'csrf' => Csrf::token(),
        ]);

        $this->renderAdmin($admin, $isEdit ? 'Edit user #' . $id : 'Create user', $body, [
            'breadcrumb' => $isEdit
                ? ['Users' => '/admin/users', 'Edit user #' . $id => null]
                : ['Users' => '/admin/users', 'Create' => null],
            'actions' => '<a class="btn btn-secondary btn-sm" href="/admin/users">'
                . admin_icon('chevron-left', 14) . ' Back to users</a>',
        ]);
    }

    private function userDetail(array $admin, \PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('SELECT u.*, p.first_name, p.last_name, p.city, p.state, p.moderation_status FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            $this->renderAdmin($admin, 'User', $this->errorPanel('User not found.', 'No account exists with ID ' . $id . '.'), [
                'breadcrumb' => ['Users' => '/admin/users', 'Not found' => null],
            ]);
            return;
        }
        unset($user['password_hash'], $user['totp_secret']);

        $safe = static function (\PDO $p, string $sql, array $params): array {
            try {
                $s = $p->prepare($sql);
                $s->execute($params);
                return $s->fetchAll();
            } catch (\Throwable $e) {
                return [];
            }
        };

        $purchases = $safe($pdo, 'SELECT * FROM purchases WHERE user_id = :u ORDER BY id DESC LIMIT 20', [':u' => $id]);
        $memberships = $safe($pdo, 'SELECT * FROM memberships WHERE user_id = :u ORDER BY id DESC LIMIT 10', [':u' => $id]);
        $audit = $safe(
            $pdo,
            "SELECT * FROM audit_logs WHERE actor_user_id = :u1 OR (entity_type = 'user' AND entity_id = :e) ORDER BY id DESC LIMIT 20",
            [':u1' => $id, ':e' => (string) $id]
        );

        $body = $this->partial('admin/views/user_detail', [
            'u' => $user,
            'purchases' => $purchases,
            'memberships' => $memberships,
            'audit' => $audit,
            'admin' => $admin,
            'csrf' => Csrf::token(),
        ]);

        $this->renderAdmin($admin, 'User #' . $id, $body, [
            'breadcrumb' => ['Users' => '/admin/users', 'User #' . $id => null],
            'subtitle' => trim(((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? ''))) ?: null,
            'actions' => $this->userDetailActions($admin, $user),
        ]);
    }

    /** Action buttons for the user detail header. */
    private function userDetailActions(array $admin, array $user): string
    {
        $id = (int) $user['id'];
        $isSuper = ($admin['role'] ?? '') === 'super_admin';
        $isSelf = $id === (int) $admin['id'];
        $privileged = in_array((string) $user['role'], \Matrimony\Services\AdminUserService::PRIVILEGED_ROLES, true);
        $canEdit = !$isSelf && ($isSuper || !$privileged);

        $html = '<a class="btn btn-secondary btn-sm" href="/admin/users">'
            . admin_icon('chevron-left', 14) . ' All users</a>';

        if ($canEdit) {
            $html .= '<a class="btn btn-primary btn-sm" href="/admin/users/' . $id . '/edit">'
                . admin_icon('edit', 14) . ' Edit user</a>';
        }

        if ((string) $user['account_status'] === 'deleted') {
            if ($isSuper || !$privileged) {
                $html .= '<form method="POST" action="/api/admin/users/restore" class="d-inline"'
                    . ' data-confirm="Restore account #' . $id . '? The member will be able to sign in again."'
                    . ' data-confirm-title="Restore account" data-confirm-text="Restore">'
                    . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
                    . '<input type="hidden" name="user_id" value="' . $id . '">'
                    . '<input type="hidden" name="return_to" value="/admin/users/' . $id . '">'
                    . '<button class="btn btn-success btn-sm" type="submit">'
                    . admin_icon('refresh', 14) . ' Restore</button></form>';
            }
        } elseif (!$isSelf && ($isSuper || !$privileged)) {
            $html .= '<a class="btn btn-danger btn-sm" href="/admin/users/' . $id . '/edit#danger">'
                . admin_icon('ban', 14) . ' Delete</a>';
        }

        return $html;
    }

    private function profiles(array $admin): void
    {
        try {
            $pdo = Connection::pdo();
            $q = substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
            $mod = (string) ($_GET['mod'] ?? '');
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per = 25;
            $off = ($page - 1) * $per;

            $where = ['1=1'];
            $params = [];
            if ($q !== '') {
                $where[] = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR u.email LIKE :q3)';
                $like = '%' . $q . '%';
                $params[':q1'] = $like;
                $params[':q2'] = $like;
                $params[':q3'] = $like;
            }
            $hasMod = \Matrimony\Database\Schema::has('profiles', 'moderation_status');
            if ($hasMod && in_array($mod, ['pending', 'approved', 'rejected', 'hidden', 'flagged'], true)) {
                $where[] = 'p.moderation_status = :m';
                $params[':m'] = $mod;
            }

            $w = implode(' AND ', $where);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id WHERE $w");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $modSelect = $hasMod ? 'p.moderation_status' : "'approved' AS moderation_status";
            $stmt = $pdo->prepare("SELECT p.user_id, p.first_name, p.last_name, p.gender, p.city, p.state, $modSelect, u.email FROM profiles p JOIN users u ON u.id = p.user_id WHERE $w ORDER BY p.user_id DESC LIMIT $per OFFSET $off");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $body = $this->partial('admin/views/profiles', [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per' => $per,
                'q' => $q,
                'mod' => $mod,
                'hasMod' => $hasMod,
                'csrf' => Csrf::token(),
            ]);
            $this->renderAdmin($admin, 'Profiles & moderation', $body, [
                'breadcrumb' => ['Profiles' => null],
                'subtitle' => 'Approve, hide or flag member profiles.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Profiles & moderation', $this->errorPanel('Could not load profiles.', $this->safeError($e)), [
                'breadcrumb' => ['Profiles' => null],
            ]);
        }
    }

    private function plans(array $admin, bool $isSuper): void
    {
        try {
            $pdo = Connection::pdo();
            if (\Matrimony\Database\Schema::has('membership_plans', 'display_order')) {
                $rows = $pdo->query("SELECT * FROM membership_plans WHERE code IN ('SILVER','GOLD','PLATINUM','FREE') ORDER BY display_order")->fetchAll();
            } else {
                $rows = $pdo->query("SELECT *, price_cents AS price_paise, 'INR' AS currency, NULL AS contact_limit, 0 AS is_unlimited, 1 AS is_active, 0 AS display_order, NULL AS description, NULL AS slug FROM membership_plans WHERE code IN ('SILVER','GOLD','PLATINUM','FREE')")->fetchAll();
            }
            $body = $this->partial('admin/views/plans', ['rows' => $rows, 'isSuper' => $isSuper, 'csrf' => Csrf::token()]);
            $this->renderAdmin($admin, 'Plans & pricing', $body, [
                'breadcrumb' => ['Plans' => null],
                'subtitle' => 'Pricing is stored in paise. Edits never alter existing purchases.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Plans & pricing', $this->errorPanel('Could not load plans.', $this->safeError($e)), [
                'breadcrumb' => ['Plans' => null],
            ]);
        }
    }

    private function payments(array $admin, bool $isSuper): void
    {
        try {
            $pdo = Connection::pdo();
            $status = (string) ($_GET['status'] ?? 'awaiting_admin');
            $allowed = ['awaiting_admin', 'all', 'paid_verified', 'failed', 'refunded', 'disputed', 'needs_review', 'approved_activated'];
            if (!in_array($status, $allowed, true)) {
                $status = 'awaiting_admin';
            }
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per = 25;
            $off = ($page - 1) * $per;

            $base = ' FROM purchases p JOIN users u ON u.id = p.user_id LEFT JOIN activation_requests ar ON ar.purchase_id = p.id ';
            $where = '';
            $params = [];

            if ($status === 'awaiting_admin') {
                $where = " WHERE ar.status IN ('awaiting_admin','on_hold')";
            } elseif ($status !== 'all') {
                $where = ' WHERE p.payment_status = :s';
                $params[':s'] = $status;
            }
            $order = $status === 'awaiting_admin' ? 'ar.requested_at ASC' : 'p.id DESC';

            $countSql = 'SELECT COUNT(*)' . $base . $where;
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $sql = 'SELECT p.*, u.email, ar.status AS activation_status, ar.requested_at, ar.id AS activation_id' . $base . $where
                . " ORDER BY {$order} LIMIT {$per} OFFSET {$off}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $body = $this->partial('admin/views/payments', [
                'rows' => $rows,
                'status' => $status,
                'page' => $page,
                'per' => $per,
                'total' => $total,
                'csrf' => Csrf::token(),
            ]);
            $this->renderAdmin($admin, 'Payments & activation', $body, [
                'breadcrumb' => ['Payments' => null],
                'subtitle' => 'Activation requires verified gateway evidence — there is no "mark as paid" shortcut.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Payments & activation', $this->errorPanel(
                'Payment tables are not set up yet. Run the migration first.',
                $this->safeError($e)
            ), ['breadcrumb' => ['Payments' => null]]);
        }
    }

    private function reports(array $admin): void
    {
        try {
            $pdo = Connection::pdo();
            $hasStatus = \Matrimony\Database\Schema::has('profile_reports', 'status');
            $statusSelect = $hasStatus ? 'r.status' : "'open' AS status";
            $filter = (string) ($_GET['status'] ?? '');
            $where = '';
            $params = [];
            if ($hasStatus && $filter !== '' && in_array($filter, ['open', 'dismissed', 'warned', 'hidden', 'suspended', 'escalated'], true)) {
                $where = ' WHERE r.status = :s';
                $params[':s'] = $filter;
            }
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per = 25;
            $off = ($page - 1) * $per;

            $base = ' FROM profile_reports r JOIN users ru ON ru.id = r.reporter_id JOIN users pu ON pu.id = r.profile_id ';
            $stmt = $pdo->prepare('SELECT COUNT(*)' . $base . $where);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT r.*, $statusSelect, ru.email AS reporter_email, pu.email AS profile_email" . $base . $where . " ORDER BY r.id DESC LIMIT {$per} OFFSET {$off}");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $body = $this->partial('admin/views/reports', [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'per' => $per,
                'status' => $hasStatus ? $filter : '',
                'hasStatus' => $hasStatus,
                'csrf' => Csrf::token(),
            ]);
            $this->renderAdmin($admin, 'Reports & moderation', $body, [
                'breadcrumb' => ['Reports' => null],
                'subtitle' => 'Member-reported profiles awaiting a moderator decision.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Reports & moderation', $this->errorPanel('Could not load reports.', $this->safeError($e)), [
                'breadcrumb' => ['Reports' => null],
            ]);
        }
    }

    private function audit(array $admin, bool $isSuper): void
    {
        try {
            $pdo = Connection::pdo();
            $per = 50;
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $off = ($page - 1) * $per;
            $action = substr(trim((string) ($_GET['action'] ?? '')), 0, 120);
            $outcome = (string) ($_GET['outcome'] ?? '');
            $mine = (string) ($_GET['mine'] ?? '');

            $where = [];
            $params = [];
            if ($action !== '') {
                $where[] = 'a.action LIKE :a';
                $params[':a'] = '%' . $action . '%';
            }
            if (in_array($outcome, ['success', 'failure'], true)) {
                $where[] = 'a.outcome = :o';
                $params[':o'] = $outcome;
            }
            if ($mine === '1' && !$isSuper) {
                $where[] = 'a.actor_user_id = :me';
                $params[':me'] = (int) $admin['id'];
            } elseif ($mine === '1') {
                $where[] = 'a.actor_user_id = :me';
                $params[':me'] = (int) $admin['id'];
            }
            $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a{$w}");
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT a.*, u.email FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id{$w} ORDER BY a.id DESC LIMIT {$per} OFFSET {$off}");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $body = $this->partial('admin/views/audit', [
                'rows' => $rows,
                'page' => $page,
                'per' => $per,
                'total' => $total,
                'action' => $action,
                'outcome' => $outcome,
                'mine' => $mine,
            ]);
            $this->renderAdmin($admin, 'Audit log', $body, [
                'breadcrumb' => ['Audit' => null],
                'subtitle' => 'Append-only record of every privileged action.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Audit log', $this->errorPanel('Audit log is not set up yet. Run the migration first.', $this->safeError($e)), [
                'breadcrumb' => ['Audit' => null],
            ]);
        }
    }

    private function admins(array $admin): void
    {
        try {
            $pdo = Connection::pdo();
            $rows = $pdo->query("SELECT id, email, role, account_status, last_login_at, created_at FROM users WHERE role IN ('admin','super_admin','moderator') ORDER BY id ASC")->fetchAll();
            $body = $this->partial('admin/views/admins', [
                'rows' => $rows,
                'csrf' => Csrf::token(),
                'me' => (int) $admin['id'],
            ]);
            $this->renderAdmin($admin, 'Admin accounts', $body, [
                'breadcrumb' => ['Admin accounts' => null],
                'subtitle' => 'Super admin only. Role changes are written to the audit log.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Admin accounts', $this->errorPanel('Could not load admin accounts.', $this->safeError($e)), [
                'breadcrumb' => ['Admin accounts' => null],
            ]);
        }
    }

    private function outbox(array $admin): void
    {
        try {
            $pdo = Connection::pdo();
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $per = 50;
            $off = ($page - 1) * $per;
            $status = (string) ($_GET['status'] ?? '');

            $where = '';
            $params = [];
            if (in_array($status, ['queued', 'sending', 'sent', 'failed'], true)) {
                $where = ' WHERE status = :s';
                $params[':s'] = $status;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM email_outbox' . $where);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT * FROM email_outbox' . $where . " ORDER BY id DESC LIMIT {$per} OFFSET {$off}");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $body = $this->partial('admin/views/outbox', [
                'rows' => $rows,
                'page' => $page,
                'per' => $per,
                'total' => $total,
                'status' => $status,
                'csrf' => Csrf::token(),
            ]);
            $this->renderAdmin($admin, 'Email outbox', $body, [
                'breadcrumb' => ['Outbox' => null],
                'subtitle' => 'Queued transactional email and delivery failures.',
            ]);
        } catch (\Throwable $e) {
            $this->renderAdmin($admin, 'Email outbox', $this->errorPanel('Email outbox is not set up yet. Run the migration first.', $this->safeError($e)), [
                'breadcrumb' => ['Outbox' => null],
            ]);
        }
    }

    // ---------- helpers ----------

    /** Run a COUNT query, returning 0 if the table/column does not exist yet. */
    private function count(\PDO $pdo, string $sql): int
    {
        try {
            return (int) $pdo->query($sql)->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Production-safe error text: detailed locally, generic in production. */
    private function safeError(\Throwable $e): string
    {
        $env = getenv('APP_ENV') ?: 'production';
        if ($env !== 'production') {
            return get_class($e) . ': ' . $e->getMessage();
        }
        return 'An internal error occurred.';
    }

    /** Shared error surface so every failure state looks identical. */
    private function errorPanel(string $message, string $detail = ''): string
    {
        $html = '<div class="admin-panel"><div class="admin-panel__body">'
            . '<div class="admin-empty">'
            . admin_icon('alert-triangle', 28)
            . '<p class="admin-empty__msg">' . e($message) . '</p>';
        if ($detail !== '') {
            $html .= '<p class="admin-empty__hint admin-mono">' . e($detail) . '</p>';
        }
        return $html . '</div></div></div>';
    }

    /**
     * @param array{title?:string,subtitle?:string,actions?:string,breadcrumb?:array} $opts
     */
    private function renderAdmin(array $admin, string $title, string $body, array $opts = []): void
    {
        $this->view('admin/views/shell', [
            'pageTitle' => 'Admin · ' . $title,
            'admin' => $admin,
            'title' => $opts['title'] ?? $title,
            'subtitle' => $opts['subtitle'] ?? null,
            'actions' => $opts['actions'] ?? '',
            'breadcrumb' => $opts['breadcrumb'] ?? [],
            'body' => $body,
        ], 'admin');
    }

    private function partial(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/app/modules/' . $view . '.php';
        return (string) ob_get_clean();
    }
}

(new AdminController())(new Request());
