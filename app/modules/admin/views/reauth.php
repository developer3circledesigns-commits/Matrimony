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
        <div class="input-group">
            <input class="form-control" id="reauthPassword" type="password" name="password"
                   required autofocus autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Show password">
                <svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>
        <div class="form-text">Re-confirmation stays valid for 15 minutes.</div>
    </div>

    <button class="btn btn-primary btn-lg w-100" type="submit">
        <?= admin_icon('lock', 17) ?> Confirm and continue
    </button>
</form>

<p class="admin-auth__foot">
    Not your request? <a href="/admin/dashboard">Cancel</a>.
</p>
