<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;

final class UsersController extends Controller
{
    private function isAjax(): bool
    {
        return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }

    protected function json($data, int $status = 200): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function __invoke(Request $request): void
    {
        $segments = array_values(array_filter(explode('/', trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/'))));
        $action = $segments[1] ?? 'login';

        if ($request::is('POST')) {
            if ($action === 'login') {
                $this->handleLogin($request);
                return;
            }
            if ($action === 'register') {
                $this->handleRegister($request);
                return;
            }
            if ($action === 'logout') {
                $logoutId = Auth::id();
                if ($logoutId !== null) {
                    \Matrimony\Security\AuditService::log($logoutId, 'user.logout', 'user', $logoutId, []);
                }
                Auth::logout();
                if ($this->isAjax()) {
                    $this->json(['success' => true, 'redirect' => '/home']);
                }
                $this->redirect('/home');
                return;
            }
        }

        if ($action === 'register') {
            $this->view('users/views/register', ['pageTitle' => 'Register', 'csrfToken' => Csrf::token(), 'errors' => [], 'input' => []], 'main');
        } else {
            $this->view('users/views/login', ['pageTitle' => 'Login', 'csrfToken' => Csrf::token(), 'error' => null, 'email' => '', 'formAction' => '/users/login'], 'main');
        }
    }

    private function handleLogin(Request $request): void
    {
        Csrf::require();

        if (!\Matrimony\Security\RateLimiter::attempt(\Matrimony\Security\RateLimiter::keyFor('login'), 10, 600)) {
            $msg = 'Too many attempts. Try again later.';
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $msg]);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => $msg, 'csrfToken' => Csrf::token(), 'email' => '', 'formAction' => '/users/login'], 'main');
            return;
        }

        $email = strtolower(trim($request::input('email', '')));
        $password = $request::input('password', '');

        $errors = [];
        if ($email === '') {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if ($password === '') {
            $errors[] = 'Password is required';
        }

        if (!empty($errors)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => implode('. ', $errors)]);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => implode('. ', $errors), 'csrfToken' => Csrf::token(), 'email' => $email, 'formAction' => '/users/login'], 'main');
            return;
        }

        $pdo = Connection::pdo();
        try {
            if (\Matrimony\Database\Schema::has('users', 'role')) {
                $stmt = $pdo->prepare("SELECT id, password_hash, is_active, account_status, role FROM users WHERE email_normalized = :email OR email = :email2 LIMIT 1");
                $stmt->execute([':email' => $email, ':email2' => $request::input('email', '')]);
            } else {
                // Legacy schema fallback (migration pending)
                $stmt = $pdo->prepare("SELECT id, password_hash, is_active FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $request::input('email', '')]);
            }
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            $user = false;
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            \Matrimony\Security\AuditService::log(null, 'user.login_failed', 'user', null, ['email' => $email], 'failure');
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Invalid email or password']);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => 'Invalid email or password', 'csrfToken' => Csrf::token(), 'email' => $email, 'formAction' => '/users/login'], 'main');
            return;
        }

        if (!$user['is_active'] || in_array($user['account_status'] ?? 'active', ['suspended', 'deactivated', 'deleted'], true)) {
            \Matrimony\Security\AuditService::log((int) $user['id'], 'user.login_blocked', 'user', $user['id'], ['status' => $user['account_status'] ?? ''], 'failure');
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Account is deactivated. Contact support.']);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => 'Account is deactivated. Contact support.', 'csrfToken' => Csrf::token(), 'email' => $email, 'formAction' => '/users/login'], 'main');
            return;
        }

        Auth::login((int) $user['id']);
        \Matrimony\Security\AuditService::log((int) $user['id'], 'user.login', 'user', (int) $user['id'], []);

        if (!empty($user['must_reauth_at'])) {
            $_SESSION['must_change_password'] = true;
        }

        // Update last login
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

        $next = (string) ($request::input('next', '') ?: ($_GET['next'] ?? ''));
        $redirect = '/matches';
        if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//') && !preg_match('#^/[\\\\]|://#', $next)) {
            $redirect = $next;
        }

        if ($this->isAjax()) {
            $this->json(['success' => true, 'redirect' => $redirect]);
        }
        $this->redirect($redirect);
    }

    private function handleRegister(Request $request): void
    {
        Csrf::require();

        $firstName = trim($request::input('first_name', ''));
        $lastName = trim($request::input('last_name', ''));
        $email = trim($request::input('email', ''));
        $password = $request::input('password', '');
        $confirmPassword = $request::input('confirm_password', '');
        $gender = $request::input('gender', '');

        $errors = [];
        if ($firstName === '') $errors[] = 'First name is required';
        if ($email === '') {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if ($password === '') {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = 'Gender is required';

        if (!empty($errors)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => $errors]);
            }
            $this->view('users/views/register', ['pageTitle' => 'Register', 'errors' => $errors, 'csrfToken' => Csrf::token(), 'input' => $_POST], 'main');
            return;
        }

        $pdo = Connection::pdo();

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Email already registered']]);
            }
            $this->view('users/views/register', ['pageTitle' => 'Register', 'errors' => ['Email already registered'], 'csrfToken' => Csrf::token(), 'input' => $_POST], 'main');
            return;
        }

        if (!\Matrimony\Security\RateLimiter::attempt(\Matrimony\Security\RateLimiter::keyFor('register'), 10, 3600)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Too many attempts. Try again later.']]);
            }
            $this->view('users/views/register', ['pageTitle' => 'Register', 'errors' => ['Too many attempts. Try again later.'], 'csrfToken' => Csrf::token(), 'input' => $_POST], 'main');
            return;
        }

        $email = strtolower($email);
        $pdo->beginTransaction();
        try {
            if (\Matrimony\Database\Schema::has('users', 'role')) {
                $stmt = $pdo->prepare("INSERT INTO users (email, email_normalized, password_hash, role, account_status) VALUES (:email, :en, :password, 'user', 'active')");
                $stmt->execute([':email' => $email, ':en' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT)]);
            } else {
                // Legacy schema fallback (migration pending)
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password)");
                $stmt->execute([':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT)]);
            }
            $userId = (int) $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth) VALUES (:uid, :fn, :ln, :gender, :dob)");
$stmt->execute([':uid' => $userId, ':fn' => $firstName, ':ln' => $lastName ?: $firstName, ':gender' => $gender, ':dob' => date('Y-m-d', strtotime('-18 years'))]);

            $pdo->commit();
            \Matrimony\Security\AuditService::log($userId, 'user.register', 'user', $userId, ['email' => $email]);
            Auth::login($userId);
            if ($this->isAjax()) {
                $this->json(['success' => true, 'redirect' => '/profile']);
            }
            $this->redirect('/profile');
        } catch (\Exception $e) {
            $pdo->rollBack();
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Registration failed. Please try again.']]);
            }
            $this->view('users/views/register', ['pageTitle' => 'Register', 'errors' => ['Registration failed. Please try again.'], 'csrfToken' => Csrf::token(), 'input' => $_POST], 'main');
        }
    }
}

(new UsersController())(new Request());
