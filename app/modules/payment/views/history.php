<div class="container py-5">
<h1>Payment history</h1>
<table class="table"><thead><tr><th>Ref</th><th>Plan</th><th>Amount</th><th>Payment</th><th>Activation</th><th>Date</th></tr></thead><tbody>
<?php foreach (($purchases ?? []) as $p): ?><tr><td><?= htmlspecialchars($p['public_reference']) ?></td><td><?= htmlspecialchars($p['plan_name_snapshot']) ?></td>
<td>₹<?= number_format($p['amount_paise']/100, 0) ?></td><td><?= htmlspecialchars($p['payment_status']) ?></td><td><?= htmlspecialchars($p['activation_status'] ?? '—') ?></td><td><?= htmlspecialchars($p['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<p class="text-muted small">A verified payment creates an activation request — it does not activate the plan by itself. Watch for “awaiting_admin” → admin approval.</p>
</div>
