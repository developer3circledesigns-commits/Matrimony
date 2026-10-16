<?php
/**
 * Admin layout — fully standalone.
 *
 * Deliberately does NOT include partials/header.php or partials/footer.php:
 * the admin panel must never render the customer navbar, marketing footer or
 * the floating "Enquiry Now" CTA (spec §6, "Admin panel specification").
 * It also does not load css/app.css, so the public site's global overrides
 * (44px buttons, floating cards, uppercase table headers) cannot bleed in.
 *
 * Expects: $pageTitle, $admin, $title, $content
 */
$pageTitle = $pageTitle ?? 'Admin';
$admin     = $admin ?? [];
$title     = $title ?? 'Admin';

$role      = (string) ($admin['role'] ?? '');
$isSuper   = $role === 'super_admin';
$adminName = (string) ($admin['email'] ?? ('#' . ($admin['id'] ?? '')));

/** Sidebar definition — single source of truth for navigation + active state. */
$adminNav = [
    [
        'label' => 'Overview',
        'items' => [
            ['path' => 'dashboard', 'text' => 'Dashboard', 'icon' => 'grid'],
        ],
    ],
    [
        'label' => 'People',
        'items' => [
            ['path' => 'users', 'text' => 'Users', 'icon' => 'users'],
            ['path' => 'profiles', 'text' => 'Profiles & moderation', 'icon' => 'id-card'],
            ['path' => 'reports', 'text' => 'Reports', 'icon' => 'flag'],
        ],
    ],
    [
        'label' => 'Revenue',
        'items' => [
            ['path' => 'payments', 'text' => 'Payments & activation', 'icon' => 'credit-card'],
            ['path' => 'plans', 'text' => 'Plans & pricing', 'icon' => 'archive'],
        ],
    ],
    [
        'label' => 'Platform',
        'items' => [
            ['path' => 'outbox', 'text' => 'Email outbox', 'icon' => 'mail'],
            ['path' => 'audit', 'text' => 'Audit log', 'icon' => 'activity'],
        ],
    ],
];
if ($isSuper) {
    $adminNav[] = [
        'label' => 'Security',
        'items' => [
            ['path' => 'admins', 'text' => 'Admin accounts', 'icon' => 'shield'],
        ],
    ];
}

/** Current section, used for the active nav item and breadcrumb. */
$currentSection = trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/admin/dashboard', PHP_URL_PATH) ?: ''), '/');
$currentSection = preg_replace('#^admin/?#', '', $currentSection) ?: 'dashboard';
$currentSection = explode('/', $currentSection)[0];

$badges = admin_nav_badges();
$brandMark = 'M';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> · Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/admin.css?v=<?= (int) @filemtime(BASE_PATH . '/css/admin.css') ?>">
<link rel="icon" type="image/png" href="/assets/fav_icon_img.png">
<script>
    // Apply the saved theme before first paint so dark mode never flashes white.
    (function () {
        try {
            var t = localStorage.getItem('adm.theme');
            if (!t) {
                t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', t);
        } catch (e) {}
    })();
</script>
</head>
<body class="admin-body">

<a class="admin-skip-link" href="#adminMain">Skip to content</a>

<div class="admin-shell">

    <!-- ------------------------------------------------------ Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
<a class="admin-sidebar__brand" href="/admin/dashboard">
    <img src="/assets/Top_nav_logo_1.png" alt="Matrimony" class="admin-brand__logo admin-brand__logo--expanded">
    <img src="/assets/fav_icon_img.png" alt="Matrimony" class="admin-brand__logo admin-brand__logo--collapsed">
</a>

        <nav class="admin-sidebar__scroll">
            <?php foreach ($adminNav as $group): ?>
                <?php
                $visible = array_filter(
                    $group['items'],
                    static fn($i) => !isset($i['super']) || $isSuper
                );
                if (!$visible) {
                    continue;
                }
                ?>
                <div class="admin-nav__section"><?= e($group['label']) ?></div>
                <?php foreach ($visible as $item): ?>
                    <?php
                    $active  = $currentSection === $item['path'];
                    $count   = $badges[$item['path']] ?? 0;
                    $isAlert = in_array($item['path'], ['payments', 'reports'], true);
                    ?>
                    <a class="admin-nav__link<?= $active ? ' is-active' : '' ?>"
                       href="/admin/<?= e($item['path']) ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>>
                        <?= admin_icon($item['icon'], 18) ?>
                        <span class="admin-nav__label"><?= e($item['text']) ?></span>
                        <?php if ($count > 0): ?>
                            <span class="admin-nav__count<?= $isAlert ? ' admin-nav__count--alert' : '' ?>"><?= $count > 99 ? '99+' : (int) $count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__foot">
            <div class="admin-sidebar__user">
                <?= admin_avatar($adminName) ?>
                <div class="admin-sidebar__user-meta">
                    <div class="admin-account">
                        <?= admin_email_lines($adminName) ?>
                    </div>
                    <div class="admin-sidebar__user-role"><?= e(str_replace('_', ' ', $role)) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="admin-scrim" data-sidebar-close></div>

    <!-- -------------------------------------------------------- Main -->
    <div class="admin-shell__main">

        <header class="admin-topbar">
            <button class="admin-topbar__toggle" type="button" data-sidebar-toggle
                    aria-controls="adminSidebar" aria-label="Toggle navigation">
                <?= admin_icon('menu', 18) ?>
            </button>

            <form class="admin-topbar__search" method="GET" action="/admin/users" role="search" data-global-search-form>
                <label class="admin-visually-hidden" for="adminGlobalSearch">Search users</label>
                <?= admin_icon('search', 16) ?>
                <input id="adminGlobalSearch" type="search" name="q" placeholder="Search users by email or name…"
                       autocomplete="off" data-global-search>
                <span class="admin-topbar__kbd">/</span>
            </form>

            <div class="admin-topbar__spacer"></div>

            <div class="admin-topbar__actions">
                <a class="admin-iconbtn" href="/" target="_blank" rel="noopener" title="View public site">
                    <?= admin_icon('external', 17) ?><span class="admin-visually-hidden">View public site</span>
                </a>

                <button class="admin-iconbtn" type="button" data-theme-toggle aria-pressed="false" title="Switch theme">
                    <span data-icon="sun"><?= admin_icon('sun', 17) ?></span>
                    <span data-icon="moon" style="display:none"><?= admin_icon('moon', 17) ?></span>
                    <span class="admin-visually-hidden">Toggle colour theme</span>
                </button>

                <div class="admin-usermenu">
                    <button class="admin-usermenu__trigger" type="button" data-usermenu-trigger
                            aria-haspopup="true" aria-expanded="false" title="<?= e($adminName) ?>">
                        <?= admin_avatar($adminName) ?>
                        <span class="admin-usermenu__name"><?= e($adminName) ?></span>
                        <?= admin_icon('chevron-down', 14) ?>
                    </button>
                    <div class="admin-dropdown" role="menu">
                        <div class="admin-dropdown__head">
                            <strong><?= e($adminName) ?></strong>
                            <span><?= e(str_replace('_', ' ', $role)) ?></span>
                        </div>
                        <a class="admin-dropdown__item" role="menuitem" href="/admin/audit">
                            <?= admin_icon('activity', 15) ?> Activity log
                        </a>
                        <a class="admin-dropdown__item" role="menuitem" href="/admin/reauth">
                            <?= admin_icon('lock', 15) ?> Re-confirm identity
                        </a>
                        <a class="admin-dropdown__item" role="menuitem" href="/" target="_blank" rel="noopener">
                            <?= admin_icon('external', 15) ?> Public site
                        </a>
                        <form method="POST" action="/users/logout">
                            <?= csrf_field() ?>
                            <button class="admin-dropdown__item admin-dropdown__item--danger" type="submit" role="menuitem">
                                <?= admin_icon('logout', 15) ?> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content" id="adminMain" tabindex="-1">
            <?= flash_render() ?>
            <?= $content ?>
        </main>
    </div>
</div>

<!-- ------------------------------------------------------------ Modals -->

<div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-labelledby="adminConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6" id="adminConfirmTitle">Please confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 admin-muted" id="adminConfirmBody"></p>
                <div class="mt-3 d-none" id="adminConfirmReasonWrap">
                    <label class="form-label" for="adminConfirmReason">Reason <span class="req" aria-hidden="true">*</span></label>
                    <textarea class="form-control" id="adminConfirmReason" rows="2" maxlength="500"
                              placeholder="Recorded in the audit log and emailed to the member"></textarea>
                    <div class="form-text" id="adminConfirmReasonHint"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="adminConfirmCancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="adminConfirmOk">Confirm</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="adminToasts" aria-live="polite" aria-relevant="additions"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/admin.js?v=<?= (int) @filemtime(BASE_PATH . '/js/admin.js') ?>"></script>
</body>
</html>
