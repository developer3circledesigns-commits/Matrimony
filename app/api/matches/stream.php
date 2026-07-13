<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Auth;

$userId = Auth::require();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Prevent timeout
if (function_exists('set_time_limit')) {
    set_time_limit(0);
}

require_once __DIR__ . '/../../modules/matches/controllers/ActionController.php';
$controller = new ActionController();

$lastCheck = time();

while (true) {
    $notifications = $controller->getNotifications($userId);

    foreach ($notifications as $notif) {
        echo "event: {$notif['type']}\n";
        echo "data: {$notif['payload']}\n\n";
    }

    if (!empty($notifications)) {
        $controller->acknowledgeNotifications($userId);
    }

    ob_flush();
    flush();

    sleep(15);

    // Check connection
    if (connection_aborted()) {
        break;
    }

    // Periodic keepalive
    if (time() - $lastCheck >= 60) {
        echo ": keepalive\n\n";
        ob_flush();
        flush();
        $lastCheck = time();
    }
}
