<div class="container py-5">
<h1 class="text-center">My membership</h1>
<?php if ($membership):
    $used = (int) $membership['contacts_used'];
    $unlimited = $membership['contacts_remaining'] === null;
    $limit = $unlimited ? null : (int) ($membership['contact_limit_snapshot'] ?? ((int) $used + (int) $membership['contacts_remaining']));
    $remaining = $unlimited ? null : (int) $membership['contacts_remaining'];
    $percent = (!$unlimited && $limit > 0) ? min(100, (int) round(($used / $limit) * 100)) : 0;
    $planName = $membership['plan_name_snapshot'] ?? $membership['plan_name'] ?? 'Plan';
?>
<div class="card mb-3 mx-auto" style="max-width:980px;"><div class="card-body">
<div class="text-center">
<h5><?= htmlspecialchars($planName) ?> <span class="badge bg-success">Active</span></h5>
<p class="mb-2">Activated: <?= htmlspecialchars($membership['activated_at'] ?? $membership['starts_at']) ?> &middot; Expires: <b><?= htmlspecialchars($membership['ends_at']) ?></b> (<?= (int) $membership['days_remaining'] ?> days left)</p>
</div>
<div class="mx-auto" style="max-width:620px">
  <div class="d-flex justify-content-between align-items-center">
    <span class="fw-medium">Profiles used for this exact plan</span>
    <strong><?= $used ?><?= $unlimited ? ' used' : ' / ' . $limit ?></strong>
  </div>
  <?php if ($unlimited): ?>
    <div class="progress mt-2" style="height:10px"><div class="progress-bar bg-success" style="width:100%"></div></div>
    <p class="text-muted small mt-2 mb-0">Unlimited contact reveals with this plan.</p>
  <?php else: ?>
    <div class="progress mt-2" style="height:10px"><div class="progress-bar <?= $remaining === 0 ? 'bg-danger' : 'bg-success' ?>" style="width:<?= $percent ?>%"></div></div>
    <p class="small mt-2 mb-0 <?= $remaining === 0 ? 'text-danger fw-medium' : 'text-muted' ?>">
      <?= $remaining === 0 ? 'Plan limit reached. Upgrade to reveal more profiles.' : ((int) $remaining . ' profile reveal' . ($remaining === 1 ? '' : 's') . ' remaining') ?>
    </p>
  <?php endif; ?>
  <?php if (!empty($membership['legacy_no_cap'])): ?>
    <div class="alert alert-warning small mt-3 mb-0">This older membership has no contact limit recorded. Please contact support before revealing more profiles.</div>
  <?php endif; ?>
</div>
</div></div>
<?php else: ?>
<div class="alert alert-warning text-center">No active membership. <a href="/plans">View plans</a></div>
<?php endif; ?>

<div class="text-center">
  <h3 class="mt-3">Payment history</h3>
  <div class="d-flex justify-content-center">
    <div class="table-responsive">
      <table class="table table-sm table-striped text-center align-middle mx-auto" style="max-width:1100px;">
        <thead class="table-light"><tr class="text-center">
          <th class="text-center">Ref</th>
          <th class="text-center">Plan</th>
          <th class="text-center">Amount</th>
          <th class="text-center">Profiles used</th>
          <th class="text-center">Payment</th>
          <th class="text-center">Activation</th>
        </tr></thead>
        <tbody class="text-center">
        <?php foreach (($purchases ?? []) as $p):
            $membershipUsed = (int) ($p['membership_contacts_used'] ?? 0);
            $membershipUnlimited = (int) ($p['is_unlimited_snapshot'] ?? 0) === 1;
            $membershipLimit = $p['contact_limit_snapshot'] !== null ? (int) $p['contact_limit_snapshot'] : null;
            $usageText = empty($p['membership_id'])
                ? '&mdash;'
                : ($membershipUnlimited ? ($membershipUsed . ' / Unlimited') : ($membershipUsed . ' / ' . ($membershipLimit ?? 0)));
        ?>
          <tr class="text-center">
            <td class="text-center"><?= htmlspecialchars($p['public_reference']) ?></td>
            <td class="text-center"><?= htmlspecialchars($p['plan_name_snapshot']) ?></td>
            <td class="text-center">&#8377;<?= number_format($p['amount_paise'] / 100, 0) ?></td>
            <td class="text-center"><?= $usageText ?></td>
            <td class="text-center"><?= htmlspecialchars($p['payment_status']) ?></td>
            <td class="text-center"><?= !empty($p['activation_status']) ? htmlspecialchars($p['activation_status']) : '&mdash;' ?></td>
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
