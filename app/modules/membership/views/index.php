<div class="container py-5">
<h1 class="text-center">My membership</h1>
<?php if ($membership): ?>
<div class="card mb-3 mx-auto" style="max-width:980px;"><div class="card-body text-center">
<h5><?= htmlspecialchars($membership['plan_name_snapshot'] ?? $membership['plan_name'] ?? 'Plan') ?> · <span class="badge bg-success">Active</span></h5>
<p>Activated: <?= htmlspecialchars($membership['activated_at'] ?? $membership['starts_at']) ?> · Expires: <b><?= htmlspecialchars($membership['ends_at']) ?></b> (<?= (int) $membership['days_remaining'] ?> days left)<br>
Contacts: <?= $membership['contacts_remaining'] === null ? '<b>Unlimited</b>' : ('<b>' . (int) $membership['contacts_used'] . '</b> used · <b>' . (int) $membership['contacts_remaining'] . '</b> remaining') ?></p>
</div></div>
<?php else: ?>
<div class="alert alert-warning text-center">No active membership. <a href="/plans">View plans</a></div>
<?php endif; ?>

<div class="text-center">
  <h3 class="mt-3">Payment history</h3>
  <div class="d-flex justify-content-center">
    <div class="table-responsive">
      <table class="table table-sm table-striped text-center align-middle mx-auto" style="max-width:980px;">
        <thead class="table-light"><tr class="text-center">
          <th class="text-center">Ref</th>
          <th class="text-center">Plan</th>
          <th class="text-center">Amount</th>
          <th class="text-center">Payment</th>
          <th class="text-center">Activation</th>
        </tr></thead>
        <tbody class="text-center">
        <?php foreach (($purchases ?? []) as $p): ?>
          <tr class="text-center">
            <td class="text-center"><?= htmlspecialchars($p['public_reference']) ?></td>
            <td class="text-center"><?= htmlspecialchars($p['plan_name_snapshot']) ?></td>
            <td class="text-center">₹<?= number_format($p['amount_paise'] / 100, 0) ?></td>
            <td class="text-center"><?= htmlspecialchars($p['payment_status']) ?></td>
            <td class="text-center"><?= htmlspecialchars($p['activation_status'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<h3 class="mt-4 text-center">Notifications</h3>
<ul class="list-group text-center"><?php foreach (($notes ?? []) as $n): ?><li class="list-group-item text-center"><b><?= htmlspecialchars($n['title']) ?></b><br><small><?= htmlspecialchars($n['message']) ?> · <?= htmlspecialchars($n['created_at']) ?></small></li><?php endforeach; ?></ul>
</div>
