<div class="container py-5" style="max-width:480px">
<h1>Forgot password</h1>
<?php if (!empty($msg)): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<button class="btn btn-primary w-100">Send reset link</button></form>
</div>
