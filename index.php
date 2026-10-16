<?php
declare(strict_types=1);

require_once __DIR__ . '/app/includes/bootstrap.php';

$segments = array_values(array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/')));

// Reject traversal / unsafe path segments before they reach any include.
foreach ($segments as $seg) {
    if ($seg === '' || $seg === '.' || $seg === '..' || strpbrk($seg, "\0\\") !== false || preg_match('/[^A-Za-z0-9._-]/', $seg)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Not found']);
        return;
    }
}

// -------------------------------------------------------
// API routing: /api/<module>/<action>[/<param>]
// -------------------------------------------------------
if (($segments[0] ?? null) === 'api') {
    $apiSegments = array_slice($segments, 1);
    // Default API responses to JSON. Endpoints (images, SSE) override as needed.
    header('Content-Type: application/json; charset=utf-8');
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
    header('Content-Type: application/json; charset=utf-8');
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
    header('Content-Type: text/html; charset=utf-8');
    $home = htmlspecialchars(function_exists('url') ? url('/') : '/', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Page not found</title><link rel="stylesheet" href="/css/app.css"></head><body>'
        . '<main class="container py-5 text-center"><h1>Page not found</h1>'
        . '<p class="text-muted">The page you requested does not exist.</p>'
        . '<p><a class="btn btn-primary" href="' . $home . '">Go home</a></p></main></body></html>';
    return;
}

require $moduleFile;
