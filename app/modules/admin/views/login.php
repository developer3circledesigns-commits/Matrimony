<?php /** Admin login. Expects $error, $email, $csrfToken. */ ?>
<h1 class="admin-auth__title">Admin sign in</h1>
<p class="admin-auth__sub">Staff access only. All sign-in attempts are recorded in the audit log.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?= admin_icon('alert-circle', 18) ?>
        <div class="admin-flash__body"><?= e((string) $error) ?></div>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/login" autocomplete="on" novalidate>
    <input type="hidden" name="csrf" value="<?= e((string) $csrfToken) ?>">

    <div class="admin-field mb-3">
        <label class="form-label" for="adminEmail">Email address <span class="req" aria-hidden="true">*</span></label>
        <input class="form-control" id="adminEmail" type="email" name="email"
               value="<?= e((string) ($email ?? '')) ?>" required autofocus
               autocomplete="username" spellcheck="false">
    </div>

    <div class="admin-field mb-4">
        <label class="form-label" for="adminPassword">Password <span class="req" aria-hidden="true">*</span></label>
        <input class="form-control" id="adminPassword" type="password" name="password"
               required autocomplete="current-password">
    </div>

    <button class="btn btn-primary btn-lg w-100" type="submit">
        <?= admin_icon('logout', 17, 'admin-icon-flip') ?> Sign in
    </button>
</form>

<p class="admin-auth__foot">
    Protected resource. <a href="/">Return to the public site</a>.
</p>
