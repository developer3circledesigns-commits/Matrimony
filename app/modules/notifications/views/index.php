<div class="container py-5">
<h1>Notifications</h1>
<form method="POST" class="mb-3"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="mark_read" value="1"><button class="btn btn-sm btn-secondary">Mark all read</button></form>
<ul class="list-group"><?php foreach (($items ?? []) as $n): ?><li class="list-group-item <?= empty($n['read_at']) ? 'list-group-item-warning' : '' ?>"><b><?= htmlspecialchars($n['title']) ?></b><br><small><?= htmlspecialchars($n['message']) ?> · <?= htmlspecialchars($n['created_at']) ?></small></li><?php endforeach; ?></ul>
</div>
