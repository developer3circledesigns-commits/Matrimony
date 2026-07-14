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

        $email = trim($request::input('email', ''));
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
        $stmt = $pdo->prepare("SELECT id, password_hash, is_active FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Invalid email or password']);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => 'Invalid email or password', 'csrfToken' => Csrf::token(), 'email' => $email, 'formAction' => '/users/login'], 'main');
            return;
        }

        if (!$user['is_active']) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Account is deactivated. Contact support.']);
            }
            $this->view('users/views/login', ['pageTitle' => 'Login', 'error' => 'Account is deactivated. Contact support.', 'csrfToken' => Csrf::token(), 'email' => $email, 'formAction' => '/users/login'], 'main');
            return;
        }

        Auth::login((int) $user['id']);

        // Update last login
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'redirect' => '/matches']);
        }
        $this->redirect('/matches');
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

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password)");
            $stmt->execute([':email' => $email, ':password' => password_hash($password, PASSWORD_BCRYPT)]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth) VALUES (:uid, :fn, :ln, :gender, :dob)");
            $stmt->execute([':uid' => $userId, ':fn' => $firstName, ':ln' => $lastName ?: $firstName, ':gender' => $gender, ':dob' => '2000-01-01']);

            $pdo->commit();
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
