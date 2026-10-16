<?php /** Re-authentication gate. Expects $error, $next, $csrfToken. */ ?>
<h1 class="admin-auth__title">Confirm your identity</h1>
<p class="admin-auth__sub">This is a high-risk action. Re-enter your password to continue.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?= admin_icon('alert-circle', 18) ?>
        <div class="admin-flash__body"><?= e((string) $error) ?></div>
    </div>
<?php endif; ?>

<form method="POST" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e((string) $csrfToken) ?>">
    <input type="hidden" name="next" value="<?= e((string) ($next ?? '/admin/dashboard')) ?>">

    <div class="admin-field mb-4">
        <label class="form-label" for="reauthPassword">Password <span class="req" aria-hidden="true">*</span></label>
        <input class="form-control" id="reauthPassword" type="password" name="password"
               required autofocus autocomplete="current-password">
        <div class="form-text">Re-confirmation stays valid for 15 minutes.</div>
    </div>

    <button class="btn btn-primary btn-lg w-100" type="submit">
        <?= admin_icon('lock', 17) ?> Confirm and continue
    </button>
</form>

<p class="admin-auth__foot">
    Not your request? <a href="/admin/dashboard">Cancel</a>.
</p>
