<div class="container py-5" style="max-width:480px">
<h1>Reset password</h1>
<?php if (!empty($done)): ?><div class="alert alert-success">Password updated. <a href="/users/login">Login</a></div>
<?php else: ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" required></div>
<div class="mb-3"><label class="form-label">Confirm</label><input class="form-control" type="password" name="confirm" required></div>
<button class="btn btn-primary w-100">Update password</button></form>
<?php endif; ?></div>
