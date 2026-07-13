<?php
declare(strict_types=1);

require_once __DIR__ . '/app/includes/bootstrap.php';

$segments = array_values(array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/')));

// -------------------------------------------------------
// API routing: /api/<module>/<action>[/<param>]
// -------------------------------------------------------
if (($segments[0] ?? null) === 'api') {
    $apiSegments = array_slice($segments, 1);
    $apiFile = BASE_PATH . '/app/api/' . implode('/', $apiSegments) . '.php';

    if (is_file($apiFile)) {
        require $apiFile;
        return;
    }

    // Fallback: walk backwards for variable-segment routes
    // e.g. /api/matches/profile/123 → api/matches/profile.php
    $remaining = [];
    while (count($apiSegments) > 1) {
        array_unshift($remaining, array_pop($apiSegments));
        $apiFile = BASE_PATH . '/app/api/' . implode('/', $apiSegments) . '.php';
        if (is_file($apiFile)) {
            $_SERVER['API_PARAMS'] = $remaining;
            require $apiFile;
            return;
        }
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
    return;
}

// -------------------------------------------------------
// Handle common icon/asset requests before module routing
// -------------------------------------------------------
if (($segments[0] ?? null) === 'favicon.ico') {
    $favicon = BASE_PATH . '/assets/fav_icon_img.png';
    if (is_file($favicon)) {
        header('Content-Type: image/png');
        readfile($favicon);
        return;
    }
    http_response_code(204);
    return;
}

if (($segments[0] ?? null) === 'robots.txt') {
    header('Content-Type: text/plain');
    echo "User-agent: *\nDisallow:\n";
    return;
}

// -------------------------------------------------------
// Module routing: /<module> → modules/<module>/index.php
// -------------------------------------------------------
$module = $segments[0] ?? 'home';
$moduleFile = BASE_PATH . '/app/modules/' . $module . '/index.php';

if (!is_file($moduleFile)) {
    http_response_code(404);
    require BASE_PATH . '/app/modules/home/index.php';
    return;
}

require $moduleFile;
