<?php
declare(strict_types=1);

/**
 * Shared admin UI primitives: icon set, status badges, money/date formatting,
 * query-string builders and a pagination control. Every admin view uses these
 * so the panel stays visually consistent instead of copy-pasting markup.
 */

/* ------------------------------------------------------------------ icons */

function admin_icon(string $name, int $size = 18, string $class = ''): string
{
    $paths = admin_icon_paths();
    if (!isset($paths[$name])) {
        return '';
    }
    $cls = 'admin-icon' . ($class !== '' ? ' ' . $class : '');
    return '<svg class="' . e($cls) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

function admin_icon_paths(): array
{
    static $icons = null;
    if ($icons !== null) {
        return $icons;
    }
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'id-card' => '<rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="9" cy="10" r="2.5"/><path d="M5.5 16a4 4 0 0 1 7 0"/><path d="M15 9h4"/><path d="M15 13h4"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V4s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.3 17.7-1.4 1.4"/><path d="m19.1 4.9-1.4 1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'menu' => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5h.01"/>',
        'alert-triangle' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'trend-up' => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'trend-down' => '<path d="m22 17-8.5-8.5-5 5L2 7"/><path d="M16 17h6v-6"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'ban' => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'archive' => '<rect x="2" y="4" width="20" height="5" rx="1"/><path d="M4 9v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9"/><path d="M10 13h4"/>',
    ];
    return $icons;
}

/* ----------------------------------------------------------------- badges */

/** Map a domain status string onto a visual tone + label. */
function admin_status(string $status): array
{
    $key = strtolower(trim($status));
    $map = [
        // accounts
        'active' => ['success', 'Active'],
        'approved' => ['success', 'Approved'],
        'paid' => ['success', 'Paid'],
        'paid_verified' => ['success', 'Verified'],
        'approved_activated' => ['success', 'Activated'],
        'sent' => ['success', 'Sent'],
        'success' => ['success', 'Success'],
        'suspended' => ['danger', 'Suspended'],
        'rejected' => ['danger', 'Rejected'],
        'failed' => ['danger', 'Failed'],
        'deleted' => ['danger', 'Deleted'],
        'deactivated' => ['muted', 'Deactivated'],
        // workflow
        'pending' => ['warning', 'Pending'],
        'pending_verification' => ['warning', 'Unverified'],
        'awaiting_admin' => ['warning', 'Awaiting review'],
        'on_hold' => ['warning', 'On hold'],
        'needs_review' => ['warning', 'Needs review'],
        'flagged' => ['warning', 'Flagged'],
        'awaiting' => ['warning', 'Awaiting'],
        'queued' => ['warning', 'Queued'],
        // informational
        'hidden' => ['muted', 'Hidden'],
        'dismissed' => ['muted', 'Dismissed'],
        'escalated' => ['info', 'Escalated'],
        'warned' => ['info', 'Warned'],
        'open' => ['info', 'Open'],
        'moderator' => ['info', 'Moderator'],
        'admin' => ['brand', 'Admin'],
        'super_admin' => ['brand', 'Super admin'],
        'user' => ['neutral', 'User'],
    ];
    if ($status === '') {
        return ['muted', '—'];
    }
    return $map[$key] ?? ['neutral', ucfirst(str_replace('_', ' ', $key))];
}

function admin_badge(string $status, ?string $label = null): string
{
    [$tone, $text] = admin_status($status);
    if ($label !== null) {
        $text = $label;
    }
    return '<span class="admin-badge admin-badge--' . e($tone) . '">' . e($text) . '</span>';
}

function admin_role_badge(string $role): string
{
    return admin_badge($role);
}

/* ------------------------------------------------------------- formatting */

/** Paise -> ₹ with thousands separators. */
function admin_money(int|float|string|null $paise, string $currency = '₹'): string
{
    $paise = (int) $paise;
    return $currency . number_format($paise / 100, 2);
}

/** Human timestamp: relative for < 24h, absolute otherwise. */
function admin_when(?string $ts, bool $relative = true): string
{
    $ts = trim((string) $ts);
    if ($ts === '' || $ts === '0000-00-00 00:00:00') {
        return '—';
    }
    $time = strtotime($ts);
    if ($time === false) {
        return e($ts);
    }
    if ($relative) {
        $diff = time() - $time;
        if ($diff < 0) {
            return date('j M Y, g:i a', $time);
        }
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . 'm ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . 'h ago';
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . 'd ago';
        }
    }
    return date('j M Y, g:i a', $time);
}

/**
 * Compact absolute date for table cells: "15 Oct 2026".
 * A full "15 Oct 2026, 3:04 pm" in every date column added ~90px per column
 * and pushed tables into horizontal scroll; the exact timestamp stays
 * available via the `title` attribute that `admin_date()` emits.
 */
function admin_date(?string $ts): string
{
    $ts = trim((string) $ts);
    if ($ts === '' || $ts === '0000-00-00 00:00:00') {
        return '—';
    }
    $time = strtotime($ts);
    if ($time === false) {
        return e(admin_truncate($ts, 14));
    }
    return date('j M Y', $time);
}

/** Full timestamp for a title attribute. */
function admin_datetime(?string $ts): string
{
    $ts = trim((string) $ts);
    if ($ts === '' || $ts === '0000-00-00 00:00:00') {
        return 'Never';
    }
    $time = strtotime($ts);
    return $time === false ? $ts : date('j M Y, g:i a', $time);
}

/**
 * Render a date cell that never widens a column but keeps the exact
 * timestamp discoverable on hover / for assistive tech.
 */
function admin_date_cell(?string $ts): string
{
    $label = admin_date($ts);
    if ($label === '—') {
        return '<span class="admin-faint">' . e($label) . '</span>';
    }
    return '<span title="' . e(admin_datetime($ts)) . '">' . e($label) . '</span>';
}

function admin_dt(?string $ts): string
{
    return admin_when($ts);
}

function admin_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (mb_strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : '?';
}

function admin_avatar(string $email, string $name = ''): string
{
    $seed = admin_initials($name !== '' ? $name : (string) $email);
    return '<span class="admin-avatar" aria-hidden="true">' . e($seed) . '</span>';
}

/**
 * Render an address over two lines, split at the "@".
 *
 * CSS wrapping alone broke addresses mid-word ("…local.tes / t"), because an
 * email has no natural break opportunity. Splitting deliberately means nothing
 * is ever hyphenated or clipped.
 */
function admin_email_lines(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '<span class="admin-faint">—</span>';
    }
    $at = strrpos($email, '@');
    if ($at === false || $at === 0) {
        return '<span class="admin-account__name">' . e($email) . '</span>';
    }
    return '<span class="admin-account__name">' . e(substr($email, 0, $at)) . '</span>'
        . '<span class="admin-account__domain">' . e(substr($email, $at)) . '</span>';
}

function admin_truncate(?string $text, int $len = 60): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '—';
    }
    if (mb_strlen($text) <= $len) {
        return e($text);
    }
    return e(mb_substr($text, 0, $len)) . '…';
}

/* -------------------------------------------------------- query + paging */

/** Build a query string from the current $_GET with overrides applied. */
function admin_query(array $overrides = []): string
{
    $params = $_GET;
    unset($params['page']);
    // Internal routing hint used by admin_pagination(); never emit it.
    unset($params['__route']);
    foreach ($overrides as $k => $v) {
        if ($k === '__route') {
            continue;
        }
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    if (!$params) {
        return '';
    }
    return '?' . http_build_query($params);
}

/**
 * Render a SaaS-style pagination bar.
 *
 * @param int   $page   current 1-based page
 * @param int   $total  total row count
 * @param int   $per    rows per page
 * @param array $params query overrides for the links (e.g. ['q' => $q])
 */
function admin_pagination(int $page, int $total, int $per = 20, array $params = [], string $label = 'records'): string
{
    $page = max(1, $page);
    $per = max(1, $per);
    $pages = (int) ceil($total / $per);
    if ($total === 0) {
        return '';
    }

    $from = ($page - 1) * $per + 1;
    $to = min($total, $page * $per);

    $summary = '<span class="admin-pager__summary">Showing <strong>' . $from . '–' . $to . '</strong> of <strong>'
        . number_format($total) . '</strong> ' . e($label) . '</span>';

    if ($pages < 2) {
        return '<div class="admin-pager admin-pager--single">' . $summary . '</div>';
    }

    $link = function (int $p, string $text, bool $disabled = false, bool $active = false, string $icon = '') use ($params): string {
        $route = (string) ($params['__route'] ?? '');
        $qp = $params;
        unset($qp['__route']);
        if ($disabled) {
            return '<li class="page-item disabled"><span class="page-link">' . $icon . e($text) . '</span></li>';
        }
        $cls = 'page-item' . ($active ? ' active' : '');
        $aria = $active ? ' aria-current="page"' : '';
        return '<li class="' . $cls . '"><a class="page-link" href="/admin/'
            . e($route) . admin_query($qp + ['page' => $p])
            . '"' . $aria . '>' . $icon . e($text) . '</a></li>';
    };

    $items = '';
    $items .= $link(max(1, $page - 1), 'Prev', $page <= 1, false, admin_icon('chevron-left', 15));

    $window = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
            $window[] = $i;
        }
    }
    $prev = 0;
    foreach ($window as $i) {
        if ($prev && $i - $prev > 1) {
            $items .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $items .= $link($i, (string) $i, false, $i === $page);
        $prev = $i;
    }

    $items .= $link(min($pages, $page + 1), 'Next', $page >= $pages, false, admin_icon('chevron-right', 15));

    return '<div class="admin-pager">'
        . $summary
        . '<nav aria-label="Pagination"><ul class="pagination mb-0">' . $items . '</ul></nav>'
        . '</div>';
}

/** Sortable column header link. */
function admin_sort_link(string $key, string $label, string $current): string
{
    $isCurrent = $current === $key;
    $next = $isCurrent && $key === 'newest' ? 'email' : $key;
    $href = '/admin/users' . admin_query(['sort' => $next, 'page' => null]);
    $icon = $isCurrent ? ' <span class="admin-sort__icon">' . admin_icon('chevron-down', 14) . '</span>' : '';
    return '<th scope="col"><a class="admin-sort' . ($isCurrent ? ' is-active' : '') . '" href="' . e($href) . '">'
        . e($label) . $icon . '</a></th>';
}

/* ---------------------------------------------------------- form helpers */

function admin_select(string $name, array $options, string $selected, string $ariaLabel = '', string $extraClass = ''): string
{
$html = '<select name="' . e($name) . '" id="' . e($name) . '" class="form-select form-select-sm ' . e($extraClass) . '"'
. ($ariaLabel !== '' ? ' aria-label="' . e($ariaLabel) . '"' : '') . '>';
    foreach ($options as $value => $label) {
        $isSel = (string) $value === $selected;
        $html .= '<option value="' . e((string) $value) . '"' . ($isSel ? ' selected' : '') . '>' . e((string) $label) . '</option>';
    }
    return $html . '</select>';
}

/** Filter chip row (used for payments status tabs). */
function admin_chips(string $route, array $chips, string $current, string $param = 'status'): string
{
    $html = '<div class="admin-chips" role="group" aria-label="Filter">';
    foreach ($chips as $value => $label) {
        $active = (string) $value === $current;
        $href = '/admin/' . e($route) . admin_query([$param => $value === '' ? null : $value, 'page' => null]);
        $html .= '<a class="admin-chip' . ($active ? ' is-active' : '') . '" href="' . e($href) . '"'
            . ($active ? ' aria-current="true"' : '') . '>' . e($label) . '</a>';
    }
    return $html . '</div>';
}

function admin_empty(string $message, string $hint = ''): string
{
    $html = '<div class="admin-empty">' . admin_icon('inbox', 26)
        . '<p class="admin-empty__msg">' . e($message) . '</p>';
    if ($hint !== '') {
        $html .= '<p class="admin-empty__hint">' . e($hint) . '</p>';
    }
    return $html . '</div>';
}

/* ------------------------------------------------------- form rendering */

/**
 * Resolve a form control's current value: submitted input wins (so a rejected
 * submission is redisplayed as typed), then the stored record, then a default.
 */
function admin_field_value(array $old, ?array $record, string $key, string $default = ''): string
{
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($record !== null && array_key_exists($key, $record) && $record[$key] !== null) {
        return (string) $record[$key];
    }
    return $default;
}

function admin_field_error(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }
    return '<div class="invalid-feedback d-block">' . e((string) $errors[$key]) . '</div>';
}

function admin_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/** Text / number / date / email input with label, hint and error slot. */
function admin_input(string $key, string $label, array $ctx, array $opts = []): string
{
    $old = $ctx['old'];
    $record = $ctx['record'];
    $errors = $ctx['errors'];

    $type = (string) ($opts['type'] ?? 'text');
    $value = admin_field_value($old, $record, $key, (string) ($opts['default'] ?? ''));
    $required = !empty($opts['required']);
    $hint = (string) ($opts['hint'] ?? '');
    $placeholder = (string) ($opts['placeholder'] ?? '');
    $min = $opts['min'] ?? null;
    $max = $opts['max'] ?? null;
    $step = $opts['step'] ?? null;
    $width = (string) ($opts['width'] ?? '');

    $html = '<div class="admin-field' . ($width !== '' ? ' ' . e($width) : '') . '">';
    $html .= '<label class="form-label" for="f_' . e($key) . '">' . e($label)
        . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';

    $input = '<input class="form-control' . admin_invalid($errors, $key) . '"'
        . ' id="f_' . e($key) . '" name="' . e($key) . '" type="' . e($type) . '"'
        . ' value="' . e($value) . '"'
        . ($required ? ' required' : '')
        . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
        . ($min !== null ? ' min="' . e((string) $min) . '"' : '')
        . ($max !== null ? ' max="' . e((string) $max) . '"' : '')
        . ($step !== null ? ' step="' . e((string) $step) . '"' : '')
        . ($hint !== '' ? ' aria-describedby="hint_' . e($key) . '"' : '')
        . '>';

    if ($type === 'password') {
        $html .= '<div class="input-group">' . $input
            . '<button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Show password">'
            . '<svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
            . '<svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            . '</button></div>';
    } else {
        $html .= $input;
    }

    if ($hint !== '') {
        $html .= '<div class="form-text" id="hint_' . e($key) . '">' . e($hint) . '</div>';
    }
    $html .= admin_field_error($errors, $key);
    return $html . '</div>';
}

/** Select with label, options map, hint and error slot. */
function admin_input_select(string $key, string $label, array $options, array $ctx, array $opts = []): string
{
    $old = $ctx['old'];
    $record = $ctx['record'];
    $errors = $ctx['errors'];
    $selected = admin_field_value($old, $record, $key, (string) ($opts['default'] ?? array_key_first($options)));
    $required = !empty($opts['required']);
    $hint = (string) ($opts['hint'] ?? '');
    $disabled = !empty($opts['disabled']);
    $width = (string) ($opts['width'] ?? '');

    $html = '<div class="admin-field' . ($width !== '' ? ' ' . e($width) : '') . '">';
    $html .= '<label class="form-label" for="f_' . e($key) . '">' . e($label)
        . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    $html .= '<select class="form-select' . admin_invalid($errors, $key) . '"'
        . ' id="f_' . e($key) . '" name="' . e($key) . '"'
        . ($required ? ' required' : '')
        . ($disabled ? ' disabled' : '')
        . ($hint !== '' ? ' aria-describedby="hint_' . e($key) . '"' : '') . '>';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . e((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>'
            . e((string) $text) . '</option>';
    }
    $html .= '</select>';
    if ($disabled) {
        $html .= '<input type="hidden" name="' . e($key) . '" value="' . e($selected) . '">';
    }
    if ($hint !== '') {
        $html .= '<div class="form-text" id="hint_' . e($key) . '">' . e($hint) . '</div>';
    }
    $html .= admin_field_error($errors, $key);
    return $html . '</div>';
}

/**
 * Multi-select rendered as a compact, collapsing checklist dropdown.
 *
 * The control is a single-line summary (so it lines up with every other input)
 * that expands into a panel of checkboxes. Checkboxes post `key[]`, preceded
 * by a hidden scalar field: when nothing is ticked PHP submits the empty
 * scalar, so the value can be cleared rather than silently retained. Values
 * may be an array or a comma-separated string (as decoded from stored JSON).
 */
function admin_input_multiselect(string $key, string $label, array $options, array $ctx, array $opts = []): string
{
    $old = $ctx['old'];
    $record = $ctx['record'];
    $errors = $ctx['errors'];

    if (array_key_exists($key, $old)) {
        $raw = $old[$key];
    } elseif ($record !== null && array_key_exists($key, $record)) {
        $raw = $record[$key];
    } else {
        $raw = (string) ($opts['default'] ?? '');
    }

    $selected = [];
    if (is_array($raw)) {
        foreach ($raw as $v) {
            $selected[] = (string) $v;
        }
    } elseif (is_string($raw) && trim($raw) !== '' && $raw !== 'null') {
        foreach (explode(',', $raw) as $v) {
            $selected[] = trim($v);
        }
    }

    $hint = (string) ($opts['hint'] ?? '');
    $width = (string) ($opts['width'] ?? '');
    $empty = (string) ($opts['placeholder'] ?? 'Nothing selected');

    $labels = [];
    foreach ($options as $value => $text) {
        if (in_array((string) $value, $selected, true)) {
            $labels[] = (string) $text;
        }
    }
    if ($labels === []) {
        $summary = $empty;
    } elseif (count($labels) > 3) {
        $summary = count($labels) . ' selected';
    } else {
        $summary = implode(', ', $labels);
    }

    $html = '<div class="admin-field' . ($width !== '' ? ' ' . e($width) : '') . '">';
    $html .= '<label class="form-label" for="f_' . e($key) . '">' . e($label) . '</label>';
    // Empty scalar companion so clearing every box still submits a value.
    $html .= '<input type="hidden" name="' . e($key) . '" value="">';
    $html .= '<details class="admin-ms' . admin_invalid($errors, $key) . '" data-admin-ms data-empty="' . e($empty) . '">';
    $html .= '<summary class="admin-ms__control" id="f_' . e($key) . '"'
        . ($hint !== '' ? ' aria-describedby="hint_' . e($key) . '"' : '') . '>'
        . '<span class="admin-ms__value' . ($labels === [] ? ' is-placeholder' : '') . '">' . e($summary) . '</span>'
        . '</summary>';
    $html .= '<div class="admin-ms__panel" role="group" aria-label="' . e($label) . '" aria-multiselectable="true">';
    foreach ($options as $value => $text) {
        $isSel = in_array((string) $value, $selected, true);
        $html .= '<label class="admin-ms__option">'
            . '<input type="checkbox" name="' . e($key) . '[]" value="' . e((string) $value) . '"'
            . ($isSel ? ' checked' : '') . '>'
            . '<span class="admin-ms__option-label">' . e((string) $text) . '</span>'
            . '</label>';
    }
    $html .= '</div></details>';
    if ($hint !== '') {
        $html .= '<div class="form-text" id="hint_' . e($key) . '">' . e($hint) . '</div>';
    }
    $html .= admin_field_error($errors, $key);
    return $html . '</div>';
}

/** Textarea with label and error slot. */
function admin_input_textarea(string $key, string $label, array $ctx, array $opts = []): string
{
    $old = $ctx['old'];
    $record = $ctx['record'];
    $errors = $ctx['errors'];
    $value = admin_field_value($old, $record, $key, (string) ($opts['default'] ?? ''));
    $hint = (string) ($opts['hint'] ?? '');
    $rows = (int) ($opts['rows'] ?? 3);
    $max = (int) ($opts['max'] ?? 2000);

    $html = '<div class="admin-field' . (isset($opts['width']) ? ' ' . e((string) $opts['width']) : '') . '">';
    $html .= '<label class="form-label" for="f_' . e($key) . '">' . e($label) . '</label>';
    $html .= '<textarea class="form-control' . admin_invalid($errors, $key) . '"'
        . ' id="f_' . e($key) . '" name="' . e($key) . '" rows="' . $rows . '" maxlength="' . $max . '"'
        . ' placeholder="' . e((string) ($opts['placeholder'] ?? '')) . '"'
        . ($hint !== '' ? ' aria-describedby="hint_' . e($key) . '"' : '') . '>' . e($value) . '</textarea>';
    if ($hint !== '') {
        $html .= '<div class="form-text" id="hint_' . e($key) . '">' . e($hint) . '</div>';
    }
    $html .= admin_field_error($errors, $key);
    return $html . '</div>';
}

/** Checkbox / switch with label. */
function admin_input_check(string $key, string $label, array $ctx, array $opts = []): string
{
    $old = $ctx['old'];
    $record = $ctx['record'];
    $checked = array_key_exists($key, $old)
        ? !empty($old[$key])
        : (bool) ($record[$key] ?? ($opts['default'] ?? false));

    $html = '<div class="form-check admin-field admin-field--check">'
        . '<input class="form-check-input" type="checkbox" id="f_' . e($key) . '"'
        . ' name="' . e($key) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="f_' . e($key) . '">' . e($label) . '</label>';
    if (!empty($opts['hint'])) {
        $html .= '<div class="form-text ms-4">' . e((string) $opts['hint']) . '</div>';
    }
    return $html . '</div>';
}

/**
 * One titled block of fields inside the create/edit form.
 *
 * @param array<string,string> $fields pre-rendered control HTML
 */
function admin_form_section(string $title, string $description, array $fields, string $icon = ''): string
{
    $html = '<section class="admin-formsection">'
        . '<div class="admin-formsection__head">';
    if ($icon !== '') {
        $html .= '<span class="admin-formsection__icon">' . admin_icon($icon, 17) . '</span>';
    }
    $html .= '<div><h2 class="admin-formsection__title">' . e($title) . '</h2>';
    if ($description !== '') {
        $html .= '<p class="admin-formsection__desc">' . e($description) . '</p>';
    }
    $html .= '</div></div>'
        . '<div class="admin-formsection__body">' . implode('', $fields) . '</div>'
        . '</section>';
    return $html;
}

/**
 * Sidebar badge counts. Returns [] silently when the schema has not been
 * migrated yet — the panel must still render.
 *
 * @return array<string,int>
 */
function admin_nav_badges(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $pdo = \Matrimony\Database\Connection::pdo();
        $queries = [
            'payments' => "SELECT COUNT(*) FROM activation_requests WHERE status IN ('awaiting_admin','on_hold')",
            'reports' => "SELECT COUNT(*) FROM profile_reports WHERE status = 'open'",
            'outbox' => "SELECT COUNT(*) FROM email_outbox WHERE status IN ('pending','queued','failed')",
        ];
        foreach ($queries as $key => $sql) {
            try {
                $cache[$key] = (int) $pdo->query($sql)->fetchColumn();
            } catch (\Throwable $e) {
                $cache[$key] = 0;
            }
        }
    } catch (\Throwable $e) {
        return [];
    }
    return $cache;
}
