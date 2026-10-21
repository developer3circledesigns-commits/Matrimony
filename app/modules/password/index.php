<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;
use Matrimony\Security\RateLimiter;
use Matrimony\Security\AuditService;

final class PasswordController extends Controller
{
    public function __invoke(Request $request): void
    {
        $action = Request::segment(1) ?? 'forgot';
        if ($action === 'reset') {
            $this->reset($request);
            return;
        }
        $this->forgot($request);
    }

    private function forgot(Request $request): void
    {
        $msg = null;
        if ($request::is('POST')) {
            Csrf::require();
            if (!RateLimiter::attempt(RateLimiter::keyFor('pwd-forgot'), 5, 3600)) {
                $msg = 'If an account exists, a reset link has been sent.';
            } else {
                $email = strtolower(trim((string) $request::input('email', '')));
                $pdo = Connection::pdo();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email_normalized = :e OR email = :e2 LIMIT 1');
                $stmt->execute([':e' => $email, ':e2' => $email]);
                $uid = $stmt->fetchColumn();
                if ($uid) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:u,:h,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))')
                        ->execute([':u' => $uid, ':h' => hash('sha256', $token)]);
                    $link = url('/password/reset?token=' . $token);
                    \Matrimony\Services\EmailOutboxService::queue((int) $uid, 'password_reset', [
                        'message' => 'Reset your password using the secure link below.',
                        'link' => $link,
                    ]);
                    // Log only file copy in dev (never in production response)
                    if ((getenv('APP_ENV') ?: 'production') !== 'production') {
                        $msg = 'Dev reset link: ' . $link;
                    }
                    AuditService::log((int) $uid, 'user.password_reset.requested', 'user', $uid, []);
                }
                // Generic message either way (anti-enumeration)
                $msg = $msg ?? 'If an account exists, a reset link has been sent.';
            }
        }
        $this->view('password/views/forgot', ['pageTitle' => 'Forgot password', 'msg' => $msg, 'csrfToken' => Csrf::token()], 'main');
    }

    private function reset(Request $request): void
    {
        $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        $error = null;
        $done = false;
        if ($request::is('POST')) {
            Csrf::require();
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');
            if (strlen($password) < 8 || $password !== $confirm) {
                $error = 'Password must be 8+ characters and match confirmation.';
            } else {
                $pdo = Connection::pdo();
                $s = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = :h LIMIT 1');
                $s->execute([':h' => hash('sha256', $token)]);
                $row = $s->fetch();
                if (!$row || $row['used_at'] || strtotime($row['expires_at']) < time()) {
                    $error = 'Link is invalid or expired.';
                } else {
                    $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['user_id']]);
                    $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = :id')->execute([':id' => $row['id']]);
                    \Matrimony\Http\Auth::revokeUserSessions((int) $row['user_id']);
                    AuditService::log((int) $row['user_id'], 'user.password_reset.completed', 'user', $row['user_id'], []);
                    $done = true;
                }
            }
        }
        $this->view('password/views/reset', ['pageTitle' => 'Reset password', 'token' => $token, 'error' => $error, 'done' => $done, 'csrfToken' => Csrf::token()], 'main');
    }
}

(new PasswordController())(new Request());
