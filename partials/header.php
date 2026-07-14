<?php
$pageTitle = $pageTitle ?? 'Matrimony';

// Determine auth status from session
$isLoggedIn = !empty($_SESSION['user_id']);

// Get current URI path to determine active nav
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Helper function to check if current path matches a nav item
function isActiveNav($path, $currentPath) {
    if ($path === '/' && $currentPath === '/') return 'active';
    if ($path !== '/' && str_starts_with($currentPath, $path)) return 'active';
    return '';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Bride Groom Matrimony - A trusted platform helping families find meaningful alliances since 2004.') ?>">
    <title><?= htmlspecialchars($pageTitle) ?> | Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="icon" type="image/png" href="/assets/fav_icon_img.png">
    <?php if ($isLoggedIn && class_exists('Matrimony\Http\Csrf')): ?>
        <?= csrf_meta() ?>
    <?php endif; ?>
</head>
<body class="skeleton-active">
<nav class="navbar navbar-expand-lg" role="navigation" aria-label="Main navigation">
  <div class="container-fluid">
    <a class="navbar-brand" href="/" aria-label="Matrimony Home">
      <img src="/assets/Top_nav_logo_1.png" alt="Matrimony Logo" width="160" height="44" class="logo-1" loading="lazy">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <div class="navbar-nav ms-auto" aria-label="User actions">
                        <a class="nav-link <?= isActiveNav('/', $currentPath) ?>" href="/">Home</a>
                        <a class="nav-link <?= isActiveNav('/about', $currentPath) ?>" href="/about">About Us</a>
                        <a class="nav-link <?= isActiveNav('/matches', $currentPath) ?>" href="/matches">Profile Matches</a>
                        <a class="nav-link <?= isActiveNav('/packages', $currentPath) ?>" href="/packages">Packages</a>
                        <a class="nav-link <?= isActiveNav('/contact', $currentPath) ?>" href="/contact">Contact</a>
                        <div class="navbar-divider"></div>
                        <?php if ($isLoggedIn): ?>
                            <a class="nav-link <?= isActiveNav('/profile', $currentPath) ?>" href="/profile">My Profile</a>
                            <a class="btn btn-outline-primary me-2" href="#" id="logout-btn" onclick="event.preventDefault(); window.handleLogout ? window.handleLogout() : (document.getElementById('logout-form').submit());">Logout</a>
                            <form id="logout-form" method="POST" action="/users/logout" style="display:none;"><?= csrf_field() ?></form>
                        <?php else: ?>
                            <a class="btn btn-outline-primary me-2 <?= isActiveNav('/users/login', $currentPath) ?>" href="/users/login">Login</a>
                            <a class="btn btn-primary <?= isActiveNav('/users/register', $currentPath) ?>" href="/users/register">Register</a>
                        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main>
