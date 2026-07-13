<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('SRC_PATH', BASE_PATH . '/src');
define('STORAGE_PATH', BASE_PATH . '/storage');

// Load composer autoloader
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoloader not found. Run 'composer install' first.\n");
    exit(1);
}
require $autoload;

// Application autoloader (PSR-4 for Matrimony\ namespace)
spl_autoload_register(function (string $class): void {
    $prefix = 'Matrimony\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = SRC_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

// Load helpers
foreach (glob(INCLUDES_PATH . '/helpers/*.php') ?: [] as $helper) {
    require_once $helper;
}

// Ensure writable storage directories exist
foreach (['sessions', 'logs', 'cache', 'emails'] as $dir) {
    $path = STORAGE_PATH . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Start session for CSRF tests
if (session_status() === PHP_SESSION_NONE) {
    session_save_path(STORAGE_PATH . '/sessions');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
