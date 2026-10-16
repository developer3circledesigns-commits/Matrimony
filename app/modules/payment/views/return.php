<div class="container py-5" style="max-width:640px">
<h1>Payment result</h1>
<p class="text-muted">This page is informational only — it never grants membership. Server verification + admin activation decide.</p>
<div id="result"><p>Checking status…</p></div>
<script>
(async () => {
  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  };
  const ref = <?= json_encode($ref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const box = document.getElementById('result');
  if (!ref) { box.textContent = ''; box.innerHTML = '<div class="alert alert-secondary">No reference provided.</div>'; return; }
  try {
    const r = await fetch('/api/payments/status?ref=' + encodeURIComponent(ref), {headers: {'Accept': 'application/json'}});
    const j = await r.json();
    if (!j.success || !j.purchase) { box.innerHTML = '<div class="alert alert-warning">Not found.</div>'; return; }
    const p = j.purchase;
    box.innerHTML = '<div class="card"><div class="card-body">Ref <b>' + esc(p.public_reference) + '</b><br>Payment: <b>' + esc(p.payment_status) + '</b><br>Activation: <b>' + esc(p.activation_status || '—') + '</b><br><a href="/membership">My membership</a></div></div>';
  } catch (e) {
    box.innerHTML = '<div class="alert alert-warning">Could not load payment status. Please try again.</div>';
  }
})();
</script>
</div>
