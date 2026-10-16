<?php
/**
 * Standalone layout for admin login / re-confirmation screens.
 * Expects: $pageTitle, $content
 */
$pageTitle = $pageTitle ?? 'Admin';
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
</head>
<body class="admin-body admin-auth">

<main class="admin-auth__card">
<div class="admin-auth__brand">
    <img src="/assets/Top_nav_logo_1.png" alt="Matrimony" class="admin-brand__logo">
</div>
    <?= flash_render() ?>
    <?= $content ?>
</main>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="adminToasts" aria-live="polite"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/admin.js?v=<?= (int) @filemtime(BASE_PATH . '/js/admin.js') ?>"></script>
</body>
</html>
