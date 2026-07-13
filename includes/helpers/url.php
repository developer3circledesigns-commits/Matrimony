<?php
function detectBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $port   = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    $url    = "{$scheme}://{$host}";
    if (!in_array($port, [80, 443], true)) {
        $url .= ":{$port}";
    }
    return $url;
}

function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($base === '') {
            $base = detectBaseUrl();
        }
    }
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $assetPath = 'assets/' . ltrim($path, '/');
    $filePath = BASE_PATH . '/public_html/' . $assetPath;
    $version = is_file($filePath) ? filemtime($filePath) : '';
    return url($assetPath) . ($version ? '?v=' . $version : '');
}
