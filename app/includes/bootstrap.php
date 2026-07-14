<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('CONFIG_PATH',     BASE_PATH . '/app/config');
define('INCLUDES_PATH',   BASE_PATH . '/app/includes');
define('SRC_PATH',        BASE_PATH . '/app/src');
define('STORAGE_PATH',    BASE_PATH . '/app/storage');

$detectedEnv = 'production';
$httpHost    = $_SERVER['HTTP_HOST'] ?? '';

if (in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
    || strpos($httpHost, 'localhost:') === 0
    || preg_match('/\.local$/', $httpHost)
    || !isset($_SERVER['SERVER_NAME'])
    || (PHP_SAPI === 'cli' && !getenv('APP_ENV'))
) {
    $detectedEnv = 'local';
}

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

$env = getenv('APP_ENV') ?: $detectedEnv;
putenv("APP_ENV={$env}");
$_ENV['APP_ENV'] = $env;

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
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_trans_sid', '0');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if ($env !== 'production') {
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
