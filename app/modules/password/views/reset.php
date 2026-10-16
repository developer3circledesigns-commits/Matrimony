<div class="container py-5" style="max-width:480px">
<h1>Reset password</h1>
<?php if (!empty($done)): ?><div class="alert alert-success">Password updated. <a href="/users/login">Login</a></div>
<?php else: ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<div class="mb-3"><label class="form-label">New password</label>
<div class="input-group">
<input class="form-control" type="password" name="password" required>
<button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Show password">
<svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
</button>
</div></div>
<div class="mb-3"><label class="form-label">Confirm</label>
<div class="input-group">
<input class="form-control" type="password" name="confirm" required>
<button class="btn btn-outline-secondary" type="button" data-pw-toggle aria-label="Show password">
<svg class="pw-icon pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="pw-icon pw-eye-off d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
</button>
</div></div>
<button class="btn btn-primary w-100">Update password</button></form>
<?php endif; ?></div>
