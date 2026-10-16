<?php
declare(strict_types=1);

/**
 * Post/Redirect/Get for admin endpoints.
 *
 * Admin views submit real HTML forms. Without this, every mutation ended with a
 * raw `{"success":true}` dumped into the browser and a refresh re-posted it.
 * JSON callers (XHR / fetch) still get JSON.
 */

function admin_json_header(): void
{
    if (!\Matrimony\Http\Authorization::isBrowserFormPost()) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

function admin_wants_json(): bool
{
    return !\Matrimony\Http\Authorization::isBrowserFormPost();
}

/**
 * Resolve where a browser form submission should land after a redirect.
 * Only relative /admin paths are accepted, so a crafted `return_to` cannot
 * turn the admin panel into an open redirect.
 */
function admin_return_to(string $fallback, ?string $candidate = null): string
{
    $candidate = $candidate ?? (string) ($_POST['return_to'] ?? '');
    if ($candidate === '') {
        return $fallback;
    }
    // Reject absolute URLs, protocol-relative URLs and backslash tricks.
    if (str_contains($candidate, '//') || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
        return $fallback;
    }
    if (str_starts_with($candidate, '/admin')) {
        return $candidate;
    }
    if (str_starts_with($candidate, '/')) {
        return '/admin' . $candidate;
    }
    return $fallback;
}

/**
 * Finish a JSON endpoint.
 *
 * @param array  $payload    Response body. `success` drives the flash tone;
 *                           `message` (or `error`) drives the flash text.
 * @param string $redirectTo Where a browser form submission lands afterwards.
 */
function admin_respond(array $payload, string $redirectTo = '/admin/dashboard', int $status = 200): void
{
    if (admin_wants_json()) {
        if ($status !== 200) {
            http_response_code($status);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ok = !array_key_exists('success', $payload) || !empty($payload['success']);
    $text = (string) ($payload['message'] ?? ($ok ? 'Done.' : ($payload['error'] ?? 'Something went wrong.')));

    if (!$ok && $status < 400) {
        $status = 422;
    }
    flash($ok ? 'success' : 'error', $text);

    if ($status >= 400 && !$ok) {
        // Keep the admin on a page they can retry from.
        $fallback = $_SERVER['HTTP_REFERER'] ?? $redirectTo;
        if (is_string($fallback) && $fallback !== '' && str_contains($fallback, '/admin')) {
            $redirectTo = $fallback;
        }
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
}
