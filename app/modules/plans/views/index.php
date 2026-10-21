<div class="plans-saas">
<?php if (!empty($migrationPending) && !empty($isAdmin)): ?>
<div class="alert alert-danger"><b>Setup incomplete:</b> payments database migration has not been applied. Purchases / activation disabled until <code>php app/database/migrate.php</code> runs.</div>
<?php elseif (!empty($migrationPending)): ?>
<div class="alert alert-warning">Membership purchases are temporarily unavailable. Please check back soon.</div>
<?php endif; ?>

<section class="plans-hero">
  <div class="container">
    <p class="plans-kicker">Matrimony Premium</p>
    <h1>Membership plans built for your search</h1>
    <p class="plans-sub">Unlock protected contact details. Payment is verified first — an admin then activates your plan. “Payment verified — awaiting admin activation” shows until activation. A contact is one unique member; repeat views of the same member never consume again.</p>
    <?php if (!empty($membership)): ?>
      <div class="plans-current">
        <div class="plans-current__info">
          <span class="plans-current__label">Current active plan</span>
          <span class="plans-current__name"><?= htmlspecialchars($membership['plan_name_snapshot'] ?? $membership['plan_name'] ?? '') ?></span>
          <?php if (!empty($membership['ends_at'])): ?>
            <span class="plans-current__meta">Expires <?= htmlspecialchars($membership['ends_at']) ?> · <?= (int) $membership['days_remaining'] ?> days left</span>
          <?php endif; ?>
        </div>
        <div class="plans-current__stat">
          <span class="plans-current__label">Contacts</span>
          <span class="plans-current__big"><?=
            $membership['contacts_remaining'] === null ? '∞' : ((int) $membership['contacts_remaining'] . '/' . (((int) $membership['contacts_used'] + (int) $membership['contacts_remaining']) ?: 1))
          ?></span>
          <span class="plans-current__hint"><?= $membership['contacts_remaining'] === null ? 'Unlimited views' : ((int) $membership['contacts_used'] . ' used so far') ?></span>
        </div>
        <div class="plans-current__stat">
          <span class="plans-current__label">Interests sent</span>
          <span class="plans-current__big"><?= (int) ($membership['interests_sent'] ?? 0) ?></span>
          <span class="plans-current__hint">for this plan</span>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($pending)): ?>
  <div class="container plans-pending">
    <div class="alert alert-warning mb-0 rounded-3">
      <b>Pending payments:</b>
      <?php foreach ($pending as $p): ?>
        <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($p['public_reference']) ?> · <?= htmlspecialchars($p['payment_status']) ?><?= !empty($p['activation']) ? ' / ' . htmlspecialchars($p['activation']) : '' ?></span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<section class="container">
  <div class="plans-grid">
    <?php foreach (($plans ?? []) as $pl):
      $slug = (string) ($pl['slug'] ?? '');
      $price = number_format((int) $pl['price_paise'] / 100, 0, '.', ',');
      $unlimited = !empty($pl['is_unlimited']);
      $contacts = $unlimited ? 'Unlimited contacts' : ((int) $pl['contact_limit'] . ' contacts');
      $featured = !empty($pl['is_featured']) || ((int) ($pl['sort_order'] ?? 99) <= 1);
    ?>
      <article class="plan-card <?= $featured ? 'is-featured' : '' ?>">
        <?php if ($featured): ?>
          <div class="plan-card__badge">Most popular</div>
        <?php endif; ?>
        <header class="plan-card__header">
          <h3 class="plan-card__name"><?= htmlspecialchars($pl['name']) ?></h3>
          <p class="plan-card__desc"><?= htmlspecialchars($pl['description'] ?? '') ?></p>
        </header>
        <div class="plan-card__price">
          <span class="plan-card__cur">₹</span>
          <span class="plan-card__amt"><?= $price ?></span>
          <span class="plan-card__per">/ <?= (int) $pl['duration_days'] ?> days</span>
        </div>
        <ul class="plan-card__features">
          <li><span class="feat-check">✓</span> <?= htmlspecialchars($contacts) ?> · reveal protected contact info</li>
          <li><span class="feat-check">✓</span> <?= (int) $pl['duration_days'] ?> days validity</li>
          <li><span class="feat-check">✓</span> Repeat views are free</li>
          <li><span class="feat-check">✓</span> Secure Razorpay checkout</li>
          <li><span class="feat-check">✓</span> Admin-activated within 24h</li>
        </ul>
        <div class="plan-card__cta">
          <?php if (empty($isLoggedIn)): ?>
            <a class="btn-plan btn-plan--ghost" href="/users/login?next=/plans">Login to buy</a>
          <?php elseif (isset($gatewayConfigured) && !$gatewayConfigured): ?>
            <button class="btn-plan btn-plan--disabled" disabled title="Online payments are not enabled yet">Buy <?= htmlspecialchars($pl['name']) ?></button>
          <?php else: ?>
            <button class="btn-plan btn-plan--primary buy-plan" data-plan="<?= htmlspecialchars($slug) ?>">Buy <?= htmlspecialchars($pl['name']) ?></button>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (empty($plans)): ?>
      <div class="plans-empty">No membership plans available yet.</div>
    <?php endif; ?>
  </div>
</section>

<?php if (isset($gatewayConfigured) && !$gatewayConfigured): ?>
  <div class="container">
    <div class="alert alert-warning mt-3">Online payments are not enabled yet. Please contact support.<?= !empty($isAdmin) ? ' <b>(Admin:</b> set <code>RAZORPAY_KEY_ID</code> / <code>RAZORPAY_KEY_SECRET</code> in the environment.)' : '' ?></div>
  </div>
<?php endif; ?>

<section class="container plans-foot">
  <div class="plans-foot__grid">
    <div>
      <h4>Why upgrade?</h4>
      <p>Free profiles hide contact details. Upgrading reveals the right people — faster, with a predictable cost per contact.</p>
    </div>
    <div>
      <h4>Secure payment</h4>
      <p>Checkout through Razorpay (UPI / cards / netbanking). We verify payment before any activation.</p>
    </div>
    <div>
      <h4>Your membership</h4>
      <p>View activations and invoices at <a href="/membership">My membership</a> and <a href="/payment/history">Payment history</a>.</p>
    </div>
  </div>
  <!-- <p class="text-muted small mt-3">Terms, refund/cancellation policy and support: see the Terms page.</p> -->
</section>

</div>

<style>
.plans-saas { font-family: Roboto, system-ui, sans-serif; }
.plans-hero { padding: 64px 0 40px; text-align: center; }
@media (max-width:640px){ .plans-hero { padding: 40px 0 28px; } }
.plans-kicker { letter-spacing: .18em; text-transform: uppercase; font-size: 12px; font-weight: 700; color: var(--brand-tertiary,#9565be); margin-bottom: 12px; text-align: center; }
.plans-hero h1 { font-size: clamp(30px, 5vw, 52px); font-weight: 800; color: var(--brand-primary,#2d1656); line-height: 1.1; margin-bottom: 16px; }
.plans-sub { max-width: 760px; margin: 0 auto; color: #5a4673; line-height: 1.5; text-align: center; }
.plans-current { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; align-items: stretch; margin-top: 28px; padding: 20px 24px; background: #fff; border: 1px solid #e9d6f3; border-radius: 16px; box-shadow: 0 8px 30px rgba(45,22,86,.06); max-width: 900px; margin-left: auto; margin-right: auto; }
.plans-current__info, .plans-current__stat { min-width: 180px; }
.plans-current__label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing:.08em; color: #8a74a8; font-weight: 700; margin-bottom: 4px; }
.plans-current__name { display: block; font-size: 20px; font-weight: 700; color: var(--brand-primary,#2d1656); }
.plans-current__meta { font-size: 13px; color: #7a6b9b; }
.plans-current__big { display:block; font-size: 30px; font-weight: 800; color: var(--brand-tertiary,#9565be); line-height: 1; }
.plans-current__hint { font-size: 12px; color: #9b8bb8; }
.plans-pending { margin-top: 12px; }
.plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 22px; padding: 30px 0 14px; }
.plan-card { position: relative; background: #fff; border: 1px solid #e9d6f3; border-radius: 18px; padding: 26px 22px; display: flex; flex-direction: column; gap: 16px; transition: transform .2s ease, box-shadow .2s ease; }
.plan-card:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(45,22,86,.10); }
.plan-card.is-featured { border-color: var(--brand-primary,#2d1656); box-shadow: 0 18px 42px rgba(45,22,86,.16); }
/* Different gradient backgrounds for each plan */
.plan-card:nth-child(1) { background: linear-gradient(135deg, #2d1656 0%, #764ba2 50%, #667eea 100%); color: #fff; }
.plan-card:nth-child(1) .plan-card__name { color: #fff; }
.plan-card:nth-child(1) .plan-card__desc { color: #e0d5f0; }
.plan-card:nth-child(1) .plan-card__cur { color: #d4bfff; }
.plan-card:nth-child(1) .plan-card__amt { color: #fff; }
.plan-card:nth-child(1) .plan-card__per { color: #e0d5f0; }
.plan-card:nth-child(1) .plan-card__features { color: #f0e6ff; }
.plan-card:nth-child(1) .plan-card__price { border-top-color: rgba(255,255,255,0.2); border-bottom-color: rgba(255,255,255,0.2); }
.plan-card:nth-child(1) .feat-check { color: #7fff7f; }
.plan-card:nth-child(2) { background: linear-gradient(135deg, #2d1656 0%, #f5576c 50%, #f093fb 100%); color: #fff; }
.plan-card:nth-child(2) .plan-card__name { color: #fff; }
.plan-card:nth-child(2) .plan-card__desc { color: #fde8f0; }
.plan-card:nth-child(2) .plan-card__cur { color: #ffd6e0; }
.plan-card:nth-child(2) .plan-card__amt { color: #fff; }
.plan-card:nth-child(2) .plan-card__per { color: #fde8f0; }
.plan-card:nth-child(2) .plan-card__features { color: #fef5f8; }
.plan-card:nth-child(2) .plan-card__price { border-top-color: rgba(255,255,255,0.2); border-bottom-color: rgba(255,255,255,0.2); }
.plan-card:nth-child(2) .feat-check { color: #7fff7f; }
.plan-card:nth-child(3) { background: linear-gradient(135deg, #16213e 0%, #00f2fe 50%, #4facfe 100%); color: #fff; }
.plan-card:nth-child(3) .plan-card__name { color: #fff; }
.plan-card:nth-child(3) .plan-card__desc { color: #e0f7ff; }
.plan-card:nth-child(3) .plan-card__cur { color: #b3e5ff; }
.plan-card:nth-child(3) .plan-card__amt { color: #fff; }
.plan-card:nth-child(3) .plan-card__per { color: #e0f7ff; }
.plan-card:nth-child(3) .plan-card__features { color: #f0fbff; }
.plan-card:nth-child(3) .plan-card__price { border-top-color: rgba(255,255,255,0.2); border-bottom-color: rgba(255,255,255,0.2); }
.plan-card:nth-child(3) .feat-check { color: #7fff7f; }
.plan-card__badge { position: absolute; top: 14px; right: 14px; background: var(--brand-primary,#2d1656); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
.plan-card__name { margin: 0 0 6px; font-size: 22px; font-weight: 800; color: var(--brand-primary,#2d1656); }
.plan-card__desc { margin: 0; color: #7a6b9b; font-size: 14px; min-height: 40px; }
.plan-card__price { display: flex; align-items: baseline; gap: 4px; padding: 8px 0; border-top: 1px solid #f3ecf8; border-bottom: 1px solid #f3ecf8; }
.plan-card__cur { font-size: 20px; font-weight: 700; color: var(--brand-tertiary,#9565be); }
.plan-card__amt { font-size: 40px; font-weight: 800; color: var(--brand-primary,#2d1656); line-height: 1; }
.plan-card__per { color: #9b8bb8; font-size: 13px; }
.plan-card__features { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; font-size: 14px; color: #4a3a63; }
.plan-card__features li { display: flex; align-items: center; gap: 8px; }
.feat-check { color: #1a7a55; font-weight: 700; }
.plan-card__cta { margin-top: auto; }
.btn-plan { display: inline-block; width: 100%; border-radius: 12px; font-weight: 700; padding: 12px 18px; border: none; cursor: pointer; text-decoration: none; }
.btn-plan--primary { background: var(--brand-primary,#2d1656); color: #fff; }
.btn-plan--primary:hover { filter: brightness(1.1); }
.btn-plan--ghost { background: transparent; border: 2px solid var(--brand-primary,#2d1656); color: var(--brand-primary,#2d1656); }
.btn-plan--disabled { background: #ddd; color: #888; cursor: not-allowed; }
.plans-empty { grid-column: 1/-1; text-align:center; padding:32px; color:#999; }
.plans-foot { padding: 30px 0 40px; text-align: center; }
.plans-foot__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 20px; padding: 24px; background: #faf7fc; border-radius: 16px; }
.plans-foot__grid h4 { color: var(--brand-primary,#2d1656); font-weight: 700; }
.plans-foot__grid p { color: #6b5a87; margin: 0; font-size: 14px; }
@media (max-width:576px){ .plan-card__desc { min-height: 0; } .plan-card__price .plan-card__amt { font-size: 32px; } }
</style>

<script>
document.querySelectorAll('.buy-plan').forEach(b => b.addEventListener('click', async () => {
  const plan = b.dataset.plan;
  b.disabled = true;
  try {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const r = await fetch('/api/payments/create-order', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({plan, csrf})});
    const j = await r.json();
    if (!j.success) { alert(j.error || 'Could not start payment'); b.disabled = false; return; }
    window.location.href = '/checkout/' + encodeURIComponent(plan) + '?order=' + encodeURIComponent(j.gateway_order_id) + '&ref=' + encodeURIComponent(j.public_reference);
  } catch (e) { alert('Network error'); b.disabled = false; }
}));
</script>
