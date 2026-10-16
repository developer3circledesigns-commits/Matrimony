<?php
/** Dashboard. Expects $stats, $admin, $csrf. */
$s = is_array($stats ?? null) ? $stats : [];

$tiles = [
    ['label' => 'Total users', 'value' => number_format((int) ($s['total_users'] ?? 0)),
     'icon' => 'users', 'tone' => 'brand', 'href' => '/admin/users',
     'meta' => (int) ($s['new_users_7d'] ?? 0) . ' joined in the last 7 days'],
    ['label' => 'Active users', 'value' => number_format((int) ($s['active_users'] ?? 0)),
     'icon' => 'user', 'tone' => 'success', 'href' => '/admin/users?status=active'],
    ['label' => 'Suspended / deactivated', 'value' => number_format((int) ($s['suspended'] ?? 0)),
     'icon' => 'ban', 'tone' => 'danger', 'href' => '/admin/users?status=suspended'],
    ['label' => 'Active memberships', 'value' => number_format((int) ($s['active_memberships'] ?? 0)),
     'icon' => 'id-card', 'tone' => 'info', 'href' => '/admin/users',
     'meta' => (int) ($s['expiring_memberships'] ?? 0) . ' expiring within 7 days'],
    ['label' => 'Awaiting activation', 'value' => number_format((int) ($s['awaiting_activation'] ?? 0)),
     'icon' => 'clock', 'tone' => 'warning', 'href' => '/admin/payments?status=awaiting_admin'],
    ['label' => 'Payments needing review', 'value' => number_format((int) ($s['needs_review'] ?? 0)),
     'icon' => 'credit-card', 'tone' => 'warning', 'href' => '/admin/payments?status=needs_review'],
    ['label' => 'Failed payments', 'value' => number_format((int) ($s['failed_payments'] ?? 0)),
     'icon' => 'alert-triangle', 'tone' => 'danger', 'href' => '/admin/payments?status=failed',
     'meta' => (int) ($s['failed_webhooks'] ?? 0) . ' failed webhooks'],
    ['label' => 'Open reports', 'value' => number_format((int) ($s['open_reports'] ?? 0)),
     'icon' => 'flag', 'tone' => 'warning', 'href' => '/admin/reports?status=open'],
    ['label' => 'Revenue (30 days)', 'value' => admin_money($s['revenue_30d_paise'] ?? 0),
     'icon' => 'trend-up', 'tone' => 'success', 'href' => '/admin/payments?status=paid_verified',
     'meta' => (int) ($s['paid_today'] ?? 0) . ' verified in the last 24h'],
    ['label' => 'Profiles pending', 'value' => number_format((int) ($s['profiles_pending'] ?? 0)),
     'icon' => 'id-card', 'tone' => 'info', 'href' => '/admin/profiles?mod=pending',
     'meta' => number_format((int) ($s['profiles_total'] ?? 0)) . ' profiles total'],
    ['label' => 'Email backlog', 'value' => number_format((int) ($s['outbox_backlog'] ?? 0)),
     'icon' => 'mail', 'tone' => 'info', 'href' => '/admin/outbox',
     'meta' => 'queued, pending or failed'],
    ['label' => 'Refunds requested', 'value' => number_format((int) ($s['refund_requests'] ?? 0)),
     'icon' => 'refresh', 'tone' => 'warning', 'href' => '/admin/payments?status=disputed',
     'meta' => (int) ($s['disputed'] ?? 0) . ' disputed payments'],
];
?>

<div class="admin-stats">
    <?php foreach ($tiles as $t): ?>
        <a class="admin-stat" href="<?= e((string) $t['href']) ?>">
            <span class="admin-stat__icon admin-stat__icon--<?= e((string) $t['tone']) ?>">
                <?= admin_icon((string) $t['icon'], 18) ?>
            </span>
            <span class="admin-stat__body">
                <p class="admin-stat__label"><?= e((string) $t['label']) ?></p>
                <p class="admin-stat__value"><?= e((string) $t['value']) ?></p>
                <?php if (!empty($t['meta'])): ?>
                    <p class="admin-stat__meta"><?= e((string) $t['meta']) ?></p>
                <?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="admin-grid-2">
    <!-- Plan mix -->
    <section class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="admin-panel__title">Active memberships by plan</h2>
                <p class="admin-panel__sub">Live count of memberships not yet expired.</p>
            </div>
            <div class="admin-panel__actions">
                <a class="btn btn-secondary btn-sm" href="/admin/plans"><?= admin_icon('edit', 14) ?> Pricing</a>
            </div>
        </div>
        <div class="admin-panel__body">
            <?php
            $planRows = is_array($s['by_plan'] ?? null) ? $s['by_plan'] : [];
            $planMax = 0;
            foreach ($planRows as $r) {
                $planMax = max($planMax, (int) ($r['c'] ?? 0));
            }
            ?>
            <?php if (!$planRows): ?>
                <?= admin_empty('No membership plans yet.', 'Create a plan to see the breakdown here.') ?>
            <?php else: ?>
                <div class="admin-tablewrap">
                    <table class="admin-table">
                        <caption class="admin-visually-hidden">Active membership count grouped by plan</caption>
                        <thead>
                            <tr>
                                <th scope="col">Plan</th>
                                <th scope="col" class="is-numeric">Active</th>
                                <th scope="col" style="width:45%">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($planRows as $r): ?>
                            <?php
                            $count = (int) ($r['c'] ?? 0);
                            $pct = $planMax > 0 ? round(($count / $planMax) * 100) : 0;
                            ?>
                            <tr>
                                <td class="admin-table__primary"><?= e((string) ($r['name'] ?? $r['code'] ?? '')) ?></td>
                                <td class="is-numeric"><?= number_format($count) ?></td>
                                <td>
                                    <div class="admin-meter">
                                        <div class="admin-meter__bar">
                                            <div class="admin-meter__fill<?= $count === 0 ? ' admin-meter__fill--muted' : '' ?>" style="width: <?= (int) $pct ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Activation queue -->
    <section class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="admin-panel__title">Activation queue</h2>
                <p class="admin-panel__sub">Oldest requests first.</p>
            </div>
            <div class="admin-panel__actions">
                <a class="btn btn-primary btn-sm" href="/admin/payments?status=awaiting_admin">
                    Open queue (<?= (int) ($s['awaiting_activation'] ?? 0) ?>)
                </a>
            </div>
        </div>
        <?php $queue = is_array($s['queue'] ?? null) ? $s['queue'] : []; ?>
        <?php if (!$queue): ?>
            <?= admin_empty('Queue is clear.', 'New activation requests appear here automatically.') ?>
        <?php else: ?>
            <div class="admin-tablewrap">
                <table class="admin-table">
                    <caption class="admin-visually-hidden">Pending activation requests</caption>
                    <thead>
                        <tr>
                            <th scope="col">Purchase</th>
                            <th scope="col">Member</th>
                            <th scope="col" class="is-numeric">Amount</th>
                            <th scope="col">Waiting</th>
                            <th scope="col"><span class="admin-visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $q): ?>
                        <tr>
                            <td class="admin-mono">#<?= (int) ($q['purchase_id'] ?? 0) ?></td>
                            <td>
                                <div class="admin-identity">
                                    <?= admin_avatar((string) ($q['email'] ?? '')) ?>
                                    <div class="admin-identity__text">
                                        <div class="admin-identity__name"><?= e((string) ($q['email'] ?? '—')) ?></div>
                                        <div class="admin-identity__meta"><?= e((string) ($q['plan_name_snapshot'] ?? '')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="is-numeric"><?= admin_money($q['amount_paise'] ?? 0) ?></td>
                            <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($q['requested_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($q['requested_at'] ?? '')) ?></td>
                            <td class="is-numeric">
                                <a class="btn btn-secondary btn-sm" href="/admin/payments?status=awaiting_admin">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Recent privileged activity -->
<section class="admin-panel" style="margin-top:1.15rem">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Recent admin activity</h2>
            <p class="admin-panel__sub">Every privileged action, newest first.</p>
        </div>
        <div class="admin-panel__actions">
            <a class="btn btn-secondary btn-sm" href="/admin/audit"><?= admin_icon('activity', 14) ?> Full audit log</a>
        </div>
    </div>
    <?php $recent = is_array($s['recent_audit'] ?? null) ? $s['recent_audit'] : []; ?>
    <?php if (!$recent): ?>
        <?= admin_empty('No admin activity recorded yet.') ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table">
                <caption class="admin-visually-hidden">Recent administrative actions</caption>
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Action</th>
                        <th scope="col">Entity</th>
                        <th scope="col">Outcome</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $a): ?>
                    <?php $outcome = (string) ($a['outcome'] ?? ''); ?>
                    <tr>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($a['created_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($a['created_at'] ?? '')) ?></td>
                        <td><?= e((string) ($a['email'] ?? ('#' . ($a['actor_user_id'] ?? 'system')))) ?></td>
                        <td class="admin-mono"><?= e((string) ($a['action'] ?? '')) ?></td>
                        <td class="admin-muted">
                            <?= e(trim(((string) ($a['entity_type'] ?? '')) . ' ' . ((string) ($a['entity_id'] ?? '')))) ?>
                        </td>
                        <td>
                            <?php if ($outcome === 'failure'): ?>
                                <span class="admin-badge admin-badge--danger">Failure</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge--success">Success</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
