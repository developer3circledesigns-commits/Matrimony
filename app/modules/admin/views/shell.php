<?php
/**
 * Page frame for a rendered admin section: breadcrumb, title, description and
 * the section-specific action buttons. The surrounding chrome (sidebar,
 * topbar, modals) lives in partials/layouts/admin.php.
 *
 * Expects: $title, $body, plus optional:
 *   $subtitle, $actions (HTML), $breadcrumb (array of label => href)
 */
$title    = $title ?? 'Admin';
$subtitle = $subtitle ?? null;
$actions  = $actions ?? '';
$crumbs   = $breadcrumb ?? [];
?>
<nav aria-label="Breadcrumb">
    <ol class="admin-breadcrumb">
        <li><a href="/admin/dashboard">Admin</a></li>
        <?php foreach ($crumbs as $crumbLabel => $crumbHref): ?>
            <li>
                <?php if (!empty($crumbHref)): ?>
                    <a href="<?= e((string) $crumbHref) ?>"><?= e((string) $crumbLabel) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= e((string) $crumbLabel) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>

<header class="admin-pagehead">
    <div class="admin-pagehead__text">
        <h1><?= e((string) $title) ?></h1>
        <?php if (!empty($subtitle)): ?>
            <p class="admin-pagehead__sub"><?= e((string) $subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?>
        <div class="admin-pagehead__actions"><?= $actions ?></div>
    <?php endif; ?>
</header>

<?= $body ?? '' ?>
