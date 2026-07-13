<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;

final class LoginController extends Controller
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
        if ($request::is('POST')) {
            $this->handleLogin();
            return;
        }

        $this->view('users/views/login', [
            'pageTitle' => 'Login',
            'csrfToken' => Csrf::token(),
            'error' => null,
            'email' => '',
            'formAction' => '/login',
        ], 'main');
    }

    private function handleLogin(): void
    {
        $env = getenv('APP_ENV') ?: 'production';
        if ($env === 'production') {
            Csrf::require();
        }

        $email = trim(Request::input('email', ''));
        $password = Request::input('password', '');

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
            $this->view('users/views/login', [
                'pageTitle' => 'Login',
                'error' => implode('. ', $errors),
                'csrfToken' => Csrf::token(),
                'email' => $email,
                'formAction' => '/login',
            ], 'main');
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
            $this->view('users/views/login', [
                'pageTitle' => 'Login',
                'error' => 'Invalid email or password',
                'csrfToken' => Csrf::token(),
                'email' => $email,
                'formAction' => '/login',
            ], 'main');
            return;
        }

        if (!$user['is_active']) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Account is deactivated. Contact support.']);
            }
            $this->view('users/views/login', [
                'pageTitle' => 'Login',
                'error' => 'Account is deactivated. Contact support.',
                'csrfToken' => Csrf::token(),
                'email' => $email,
                'formAction' => '/login',
            ], 'main');
            return;
        }

        Auth::login((int) $user['id']);
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'redirect' => '/matches']);
        }
        $this->redirect('/matches');
    }
}

(new LoginController())(new Request());
