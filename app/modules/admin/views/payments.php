<?php
/** Payments & activation queue. Expects $rows, $status, $page, $per, $total, $csrf. */
$rows = is_array($rows ?? null) ? $rows : [];

$tabs = [
    'awaiting_admin' => 'Awaiting activation',
    'needs_review' => 'Needs review',
    'all' => 'All purchases',
    'paid_verified' => 'Verified',
    'approved_activated' => 'Activated',
    'failed' => 'Failed',
    'disputed' => 'Disputed',
    'refunded' => 'Refunded',
];
?>

<?= admin_chips('payments', $tabs, (string) $status) ?>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title"><?= e((string) ($tabs[$status] ?? 'Payments')) ?></h2>
            <p class="admin-panel__sub"><?= number_format((int) $total) ?> matching record<?= ((int) $total === 1 ? '' : 's') ?>.</p>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $status === 'all' ? 'No purchases recorded yet.' : 'Nothing in this queue.',
            $status === 'awaiting_admin' ? 'Activation requests appear here as soon as a payment is verified.' : ''
        ) ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table admin-table--fixed-actions">
                <caption class="admin-visually-hidden">Payments and activation requests</caption>
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Member</th>
                        <th scope="col">Plan</th>
                        <th scope="col" class="is-numeric">Amount</th>
                        <th scope="col">Gateway</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $purchaseId    = (int) ($r['id'] ?? 0);
                    $activationId  = (int) ($r['activation_id'] ?? $purchaseId);
                    $payStatus     = (string) ($r['payment_status'] ?? '');
                    $actStatus     = (string) ($r['activation_status'] ?? $r['status'] ?? '');
                    $email         = (string) ($r['email'] ?? '');
                    $planName      = (string) ($r['plan_name_snapshot'] ?? '');
                    $reference     = (string) ($r['public_reference'] ?? ('act#' . $activationId));
                    $canActivate   = $payStatus === 'paid_verified' && in_array($actStatus, ['awaiting_admin', 'on_hold'], true);
                    $refundable    = in_array($payStatus, ['paid_verified', 'disputed'], true) && (int) ($r['amount_paise'] ?? 0) > 0;
                    $orderId       = (string) ($r['gateway_order_id'] ?? '');
                    $paymentId     = (string) ($r['gateway_payment_id'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <div class="admin-table__primary admin-mono"><?= e($reference) ?></div>
                            <div class="admin-table__sub"><?= e(admin_when((string) ($r['created_at'] ?? $r['requested_at'] ?? ''))) ?></div>
                        </td>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar($email) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e($email ?: ('#' . ($r['user_id'] ?? ''))) ?></div>
                                    <div class="admin-identity__meta">User #<?= (int) ($r['user_id'] ?? 0) ?></div>
                                </div>
                            </div>
                        </td>                        <td><?= e($planName) ?: '<span class="admin-faint">—</span>' ?></td>
                        <td class="is-numeric admin-table__primary"><?= admin_money($r['amount_paise'] ?? 0) ?></td>
                        <td class="admin-mono admin-muted" style="font-size:.72rem">
                            <?php if ($orderId !== '' || $paymentId !== ''): ?>
                                <div><?= e(admin_truncate($orderId, 14)) ?></div>
                                <div><?= e(admin_truncate($paymentId, 14)) ?></div>
                            <?php else: ?>
                                <span class="admin-faint">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= admin_badge($payStatus) ?>
                            <div class="admin-table__sub"><?= admin_badge($actStatus) ?></div>
                        </td>
                        <td class="is-numeric">
                            <div class="admin-table__actions">
                                <?php if ($canActivate): ?>
                                    <button class="btn btn-success btn-sm" type="button"
                                            data-confirm="Activate <?= e($planName) ?> for <?= e($email ?: 'this user') ?>? Start date is now and the contact allowance comes from the purchase snapshot."
                                            data-confirm-title="Activate plan"
                                            data-confirm-text="Activate"
                                            data-endpoint="/api/admin/payments/activate"
                                            data-fields='<?= e(json_encode([
                                                'activation_id' => $activationId,
                                                'mode' => 'replace',
                                                'reason' => 'Approved from admin activation queue',
                                            ], JSON_UNESCAPED_SLASHES)) ?>'>
                                        <?= admin_icon('check-circle', 13) ?> Activate
                                    </button>

                                    <button class="btn btn-outline-secondary btn-sm" type="button"
                                            data-confirm="Put this activation request on hold? A reason is required and the member is notified."
                                            data-confirm-title="Hold request"
                                            data-endpoint="/api/admin/payments/hold"
                                            data-requires-reason="1"
                                            data-fields='<?= e(json_encode(['activation_id' => $activationId], JSON_UNESCAPED_SLASHES)) ?>'>
                                        Hold
                                    </button>

                                    <button class="btn btn-outline-danger btn-sm" type="button"
                                            data-confirm="Reject this activation request? The member is notified and no membership is granted."
                                            data-confirm-title="Reject request" data-confirm-danger
                                            data-confirm-text="Reject"
                                            data-endpoint="/api/admin/payments/reject"
                                            data-requires-reason="1"
                                            data-fields='<?= e(json_encode(['activation_id' => $activationId], JSON_UNESCAPED_SLASHES)) ?>'>
                                        Reject
                                    </button>
                                <?php endif; ?>
                                <?php if ($refundable): ?>
                                    <button class="btn btn-outline-danger btn-sm" type="button"
                                            data-confirm="Record a full refund request of <?= e(admin_money($r['amount_paise'] ?? 0)) ?> for <?= e($reference) ?>? This does not move money; process it in the Razorpay dashboard afterwards."
                                            data-confirm-title="Request full refund"
                                            data-confirm-text="Record refund"
                                            data-confirm-danger
                                            data-endpoint="/api/admin/payments/refund"
                                            data-requires-reason="1"
                                            data-fields='<?= e(json_encode([
                                                'purchase_id' => $purchaseId,
                                                'amount_paise' => (int) ($r['amount_paise'] ?? 0),
                                            ], JSON_UNESCAPED_SLASHES)) ?>'>
                                        Refund
                                    </button>
                                <?php endif; ?>
                                <form method="POST" action="/api/admin/payments/reconcile" class="admin-rowform"
                                      data-confirm="Reconcile purchase <?= e($reference) ?> against the gateway?"
                                      data-confirm-title="Reconcile payment">
                                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                                    <input type="hidden" name="purchase_id" value="<?= $purchaseId ?>">
                                    <button class="btn btn-secondary btn-sm" type="submit">
                                        <?= admin_icon('refresh', 13) ?> Reconcile
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_pagination((int) $page, (int) $total, (int) $per, ['__route' => 'payments'], 'payments') ?>
    <?php endif; ?>
</section>

<div class="admin-notice" style="margin-top:1.15rem">
    <?= admin_icon('lock', 18) ?>
    <div>
        There is deliberately <strong>no "mark as paid"</strong> button. Activation requires
        <strong>paid_verified</strong> gateway evidence; refunds and disputes suspend the linked membership for review
        rather than silently rewriting payment state.
    </div>
</div>
