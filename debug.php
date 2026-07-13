<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "<h2>PHP Version: " . PHP_VERSION . "</h2>";

try {
    require_once __DIR__ . '/includes/bootstrap.php';
    echo "bootstrap.php: OK<br>";

    // Test DB connection
    $pdo = \Matrimony\Database\Connection::pdo();
    echo "DB Connection: OK<br>";

    // Test Csrf::token()
    $csrf = \Matrimony\Http\Csrf::token();
    echo "Csrf::token(): OK (" . substr($csrf, 0, 10) . "...)<br>";

    // Test MatchEngine
    $engine = new \Matrimony\Services\MatchEngine();
    echo "MatchEngine constructor: OK<br>";

    // Test CompatibilityService
    $compat = new \Matrimony\Services\CompatibilityService();
    echo "CompatibilityService constructor: OK<br>";

    echo "<h3>All checks passed</h3>";

} catch (\Throwable $e) {
    echo "<h3>ERROR</h3>";
    echo get_class($e) . ": " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
