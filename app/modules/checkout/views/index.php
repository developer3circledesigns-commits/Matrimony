<?php
$status = (string) ($purchase['payment_status'] ?? '');
$amountPaise = (int) ($purchase['amount_paise'] ?? $plan['price_paise'] ?? 0);
$currency = (string) ($purchase['currency'] ?? 'INR');
$orderId = (string) ($purchase['gateway_order_id'] ?? '');
$payable = $purchase && in_array($status, ['created', 'pending', 'failed'], true);
$terminal = in_array($status, ['paid_verified', 'refunded', 'disputed', 'cancelled', 'needs_review'], true);
$amountLabel = ($currency === 'INR' ? '₹' : $currency . ' ') . number_format($amountPaise / 100, 2);
$exp = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="container py-5" style="max-width:640px">
<h1>Checkout</h1>
<div class="card mb-3"><div class="card-body">
<h5><?= $exp($plan['name']) ?></h5>
<p>Amount: <b><?= $exp($amountLabel) ?></b> &middot; <?= !empty($plan['is_unlimited']) ? 'Unlimited contacts' : ((int) $plan['contact_limit'] . ' contacts') ?> &middot; <?= (int) $plan['duration_days'] ?> days</p>
<?php if ($purchase): ?><p class="small text-muted">Order ref: <b><?= $exp($purchase['public_reference']) ?></b> &middot; status: <?= $exp($status) ?></p><?php endif; ?>
<p class="small text-muted">Browser success screens are informational only. Your plan activates only after server verification + admin approval.</p>
</div></div>

<?php if ($purchase === false): ?>
<div class="alert alert-danger"><b>Payments are not configured yet</b> — the database migration is pending. Please try again after setup is complete.</div>
<?php elseif (isset($gatewayConfigured) && !$gatewayConfigured): ?>
<div class="alert alert-warning">Online payments are not enabled yet. Please contact support.</div>
<?php elseif (!$purchase): ?>
<div class="alert alert-warning">No pending order found. <button id="create-order" class="btn btn-sm btn-primary" data-plan="<?= $exp($plan['slug']) ?>">Create order</button></div>
<?php elseif ($terminal): ?>
<div class="alert alert-info">
<?php if ($status === 'paid_verified'): ?>
Payment verified — awaiting admin activation. <a href="/payment/history">Track status</a>
<?php elseif ($status === 'needs_review'): ?>
This payment is being reviewed by our team. No further payment is needed. <a href="/contact">Contact support</a>
<?php else: ?>
This purchase is <b><?= $exp($status) ?></b>. No further payment is required. <a href="/plans">Choose another plan</a>
<?php endif; ?>
</div>
<?php else: ?>
<?php if ($orderId === ''): ?>
<div class="alert alert-warning">Your order is not ready yet. <button id="create-order" class="btn btn-sm btn-primary" data-plan="<?= $exp($plan['slug']) ?>">Create order</button></div>
<?php else: ?>
<button id="pay-btn" class="btn btn-success btn-lg w-100">Pay <?= $exp($amountLabel) ?> securely</button>
<div id="pay-status" class="mt-3"></div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const orderId = <?= json_encode($orderId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const pubRef = <?= json_encode((string) $purchase['public_reference'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const keyId = <?= json_encode((string) $keyId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const amount = <?= $amountPaise ?>;
const currency = <?= json_encode($currency, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.getElementById('pay-btn').addEventListener('click', () => {
  if (!orderId) { alert('Order not ready'); return; }
  const rzp = new Razorpay({key: keyId, amount, currency, name: 'Matrimony', description: <?= json_encode((string) $plan['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, order_id: orderId,
    prefill: {email: <?= json_encode((string) $userEmail, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>},
    handler: async function (resp) {
      const csrf = document.querySelector('meta[name=csrf-token]')?.content || <?= json_encode((string) $csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const r = await fetch('/api/payments/verify', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({razorpay_order_id: resp.razorpay_order_id, razorpay_payment_id: resp.razorpay_payment_id, razorpay_signature: resp.razorpay_signature, csrf})});
      let j = {};
      try { j = await r.json(); } catch (e) { j = {success: false, error: 'Verification is taking longer than expected.'}; }
      const el = document.getElementById('pay-status');
      if (j.success) { el.innerHTML = '<div class="alert alert-success">Payment verified — awaiting admin activation. Ref: ' + pubRef.replace(/[<>&"\'`]/g, '') + '. <a href="/payment/history">Track status</a></div>'; }
      else { el.innerHTML = '<div class="alert alert-warning">' + String(j.error || 'Verification pending').replace(/[<>&"\'`]/g, '') + '</div>'; }
    }});
  rzp.on('payment.failed', function () { document.getElementById('pay-status').innerHTML = '<div class="alert alert-danger">Payment failed. You can retry — no membership was granted.</div>'; });
  rzp.open();
});
</script>
<?php endif; ?>
<?php endif; ?>

<script>
document.getElementById('create-order')?.addEventListener('click', async (e) => {
  const plan = e.target.dataset.plan;
  const csrf = document.querySelector('meta[name=csrf-token]')?.content || <?= json_encode((string) $csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  e.target.disabled = true;
  const r = await fetch('/api/payments/create-order', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({plan, csrf})});
  let j = {};
  try { j = await r.json(); } catch (err) { j = {}; }
  if (j.success) window.location.href = '/checkout/' + encodeURIComponent(plan) + '?order=' + encodeURIComponent(j.gateway_order_id) + '&ref=' + encodeURIComponent(j.public_reference);
  else { e.target.disabled = false; alert(j.error || 'Could not start payment'); }
});
</script>
</div>
