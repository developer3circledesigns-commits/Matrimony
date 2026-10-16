<?php
declare(strict_types=1);

/**
 * One-shot session messages for the Post/Redirect/Get flow.
 * Set in the request that mutates, read (and cleared) in the next render.
 */
function flash(string $type, string $message): void
{
    if ($message === '') {
        return;
    }
    $allowed = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function flash_pull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

/**
 * Remember a rejected form submission so the redirect target can re-render it
 * with the operator's input and per-field errors intact.
 *
 * Never stores secrets: password fields are replaced with a sentinel before
 * the data is written to the session.
 */
function flash_form(array $errors, array $old = []): void
{
    foreach (array_keys($old) as $key) {
        if (str_contains(strtolower((string) $key), 'password')) {
            $old[$key] = '';
        }
    }
    $_SESSION['flash_form'] = [
        'errors' => $errors,
        'old' => $old,
        'at' => time(),
    ];
}

/** @return array{errors:array<string,string>,old:array} */
function flash_form_pull(): array
{
    $data = $_SESSION['flash_form'] ?? null;
    unset($_SESSION['flash_form']);
    if (!is_array($data)) {
        return ['errors' => [], 'old' => []];
    }
    // Never replay a stale form state for more than 10 minutes.
    if ((time() - (int) ($data['at'] ?? 0)) > 600) {
        return ['errors' => [], 'old' => []];
    }
    return [
        'errors' => is_array($data['errors'] ?? null) ? $data['errors'] : [],
        'old' => is_array($data['old'] ?? null) ? $data['old'] : [],
    ];
}

/** Render + clear queued flashes. Safe to call once per page. */
function flash_render(): string
{
    // Bootstrap class names differ from our internal vocabulary.
    $alertClass = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];

    $out = '';
    foreach (flash_pull() as $item) {
        $type = (string) ($item['type'] ?? 'info');
        $tone = $alertClass[$type] ?? 'info';
        $msg = e((string) ($item['message'] ?? ''));
        $icon = match ($type) {
            'success' => admin_icon('check-circle', 18),
            'error' => admin_icon('alert-circle', 18),
            'warning' => admin_icon('alert-triangle', 18),
            default => admin_icon('info', 18),
        };
        $out .= '<div class="alert alert-' . $tone . ' alert-dismissible fade show admin-flash" role="alert" data-admin-autodismiss="6000">'
            . '<span class="admin-flash__icon">' . $icon . '</span>'
            . '<div class="admin-flash__body">' . $msg . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>'
            . '</div>';
    }
    return $out;
}
