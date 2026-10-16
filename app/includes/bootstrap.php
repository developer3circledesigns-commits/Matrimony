<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('CONFIG_PATH',     BASE_PATH . '/app/config');
define('INCLUDES_PATH',   BASE_PATH . '/app/includes');
define('SRC_PATH',        BASE_PATH . '/app/src');
define('STORAGE_PATH',    BASE_PATH . '/app/storage');

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// Never trust Host spoofing for env. Default to production when APP_ENV is unset.
$env = getenv('APP_ENV');
if ($env === false || $env === '') {
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    $looksLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
        || strpos($httpHost, 'localhost:') === 0
        || (bool) preg_match('/\.local$/', $httpHost)
        || PHP_SAPI === 'cli';
    // Local detection only when a .env file is present (dev checkout).
    $env = (is_file($envFile) && $looksLocal) ? 'local' : 'production';
}
putenv("APP_ENV={$env}");
$_ENV['APP_ENV'] = $env;
$appDebug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

spl_autoload_register(function (string $class): void {
    $prefix = 'Matrimony\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$composer = BASE_PATH . '/app/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
}

$helperFiles = [
    INCLUDES_PATH . '/helpers/env.php',
    INCLUDES_PATH . '/helpers/html.php',
    INCLUDES_PATH . '/helpers/url.php',
    INCLUDES_PATH . '/helpers/csrf.php',
    INCLUDES_PATH . '/helpers/flash.php',
    INCLUDES_PATH . '/helpers/admin_ui.php',
    INCLUDES_PATH . '/helpers/admin_response.php',
];
foreach ($helperFiles as $helper) {
    if (is_file($helper)) {
        require_once $helper;
    }
}

foreach (['sessions', 'logs', 'cache', 'emails'] as $dir) {
    $path = STORAGE_PATH . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

session_save_path(STORAGE_PATH . '/sessions');

if (session_status() === PHP_SESSION_NONE) {
    $fwdProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443
        || $fwdProto === 'https';
    $sessionSecureEnv = getenv('SESSION_SECURE');
    if ($sessionSecureEnv !== false && $sessionSecureEnv !== '') {
        $isSecure = filter_var($sessionSecureEnv, FILTER_VALIDATE_BOOLEAN);
    } else {
        // Secure cookies only when the request is actually HTTPS (avoids broken logins on plain HTTP).
        $isSecure = $isHttps;
    }

    session_set_cookie_params([
        'lifetime' => 604800, // 7 days — persists across browser restarts instead of being a session cookie
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '604800');
    ini_set('session.sid_length', '48');
    session_start();
}

// Idle-timeout + session binding for logged-in sessions (generous for admins)
if (!empty($_SESSION['user_id'])) {
    if (!\Matrimony\Http\Auth::validateSession(28800)) {
        // validateSession already logged the user out when binding/idle failed
    }
}

// Private cache headers for authenticated / sensitive routes
$__uri = $_SERVER['REQUEST_URI'] ?? '/';
if (!empty($_SESSION['user_id']) || preg_match('#^/(admin|profile|matches|membership|payment|plans|checkout|notifications)(/|$)#', $__uri)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}

/*
 * API responses must always be JSON. Without this, an uncaught exception or a
 * fatal error prints an HTML page inside what fetch() expects to be JSON, the
 * frontend's res.json() throws and the user only sees a bogus "Network error".
 * Only API requests are affected; normal page rendering is untouched.
 */
$__isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0
    || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

if ($__isApiRequest) {
    $__apiDebug = $appDebug;
    // Keep warnings/notices out of the JSON body; they are still logged.
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');

    $__apiErrorBody = static function (string $message) use ($__apiDebug): string {
        return (string) json_encode([
            'success' => false,
            'error'   => $__apiDebug ? $message : 'Internal server error',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    set_exception_handler(static function (\Throwable $e) use ($__apiErrorBody): void {
        error_log('[api] uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $__apiErrorBody($e->getMessage());
        exit;
    });

    register_shutdown_function(static function () use ($__apiErrorBody): void {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        error_log('[api] fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $__apiErrorBody($err['message']);
    });
}
