<div class="container py-5" style="max-width:640px">
<h1>Checkout</h1>
<div class="card mb-3"><div class="card-body">
<h5><?= htmlspecialchars($plan['name']) ?></h5>
<p>Amount: <b>₹<?= number_format($plan['price_paise'] / 100, 0) ?></b> · <?= !empty($plan['is_unlimited']) ? 'Unlimited contacts' : ((int) $plan['contact_limit'] . ' contacts') ?> · <?= (int) $plan['duration_days'] ?> days</p>
<?php if ($purchase): ?><p class="small text-muted">Order ref: <b><?= htmlspecialchars($purchase['public_reference']) ?></b> · status: <?= htmlspecialchars($purchase['payment_status']) ?></p><?php endif; ?>
<p class="small text-muted">Browser success screens are informational only. Your plan activates only after server verification + admin approval.</p>
</div></div>
<?php if ($purchase === false): ?>
<div class="alert alert-danger"><b>Payments are not configured yet</b> — the database migration is pending. Please try again after setup is complete.</div>
<?php elseif (isset($gatewayConfigured) && !$gatewayConfigured): ?>
<div class="alert alert-warning">Online payments are not enabled yet. Please contact support.</div>
<?php elseif (!$purchase): ?>
<div class="alert alert-warning">No pending order found. <button id="create-order" class="btn btn-sm btn-primary" data-plan="<?= htmlspecialchars($plan['slug']) ?>">Create order</button></div>
<?php else: ?>
<button id="pay-btn" class="btn btn-success btn-lg w-100">Pay ₹<?= number_format($plan['price_paise'] / 100, 0) ?> securely</button>
<div id="pay-status" class="mt-3"></div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const orderId = <?= json_encode($purchase['gateway_order_id']) ?>;
const pubRef = <?= json_encode($purchase['public_reference']) ?>;
const keyId = <?= json_encode($keyId) ?>;
const amount = <?= (int) $plan['price_paise'] ?>;
document.getElementById('pay-btn').addEventListener('click', () => {
  if (!orderId) { alert('Order not ready'); return; }
  const rzp = new Razorpay({key: keyId, amount, currency: 'INR', name: 'Matrimony', description: <?= json_encode($plan['name']) ?>, order_id: orderId,
    prefill: {email: <?= json_encode($userEmail) ?>},
    handler: async function (resp) {
      const csrf = document.querySelector('meta[name=csrf-token]')?.content || <?= json_encode($csrfToken) ?>;
      const r = await fetch('/api/payments/verify', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({razorpay_order_id: resp.razorpay_order_id, razorpay_payment_id: resp.razorpay_payment_id, razorpay_signature: resp.razorpay_signature, csrf})});
      const j = await r.json();
      const el = document.getElementById('pay-status');
      if (j.success) { el.innerHTML = '<div class="alert alert-success">Payment verified — awaiting admin activation. Ref: ' + pubRef + '. <a href="/payment/history">Track status</a></div>'; }
      else { el.innerHTML = '<div class="alert alert-warning">' + (j.error || 'Verification pending') + '</div>'; }
    }});
  rzp.on('payment.failed', function () { document.getElementById('pay-status').innerHTML = '<div class="alert alert-danger">Payment failed. You can retry — no membership was granted.</div>'; });
  rzp.open();
});
</script>
<?php endif; ?>
<script>
document.getElementById('create-order')?.addEventListener('click', async (e) => {
  const plan = e.target.dataset.plan;
  const csrf = document.querySelector('meta[name=csrf-token]')?.content || <?= json_encode($csrfToken) ?>;
  const r = await fetch('/api/payments/create-order', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({plan, csrf})});
  const j = await r.json();
  if (j.success) window.location.href = '/checkout/' + encodeURIComponent(plan) + '?order=' + encodeURIComponent(j.gateway_order_id) + '&ref=' + encodeURIComponent(j.public_reference);
  else alert(j.error || 'Failed');
});
</script>
</div>
