<?php
/** Single user view. Expects $u, $purchases, $memberships, $audit, $admin, $csrf. */
$isSuper = (($admin['role'] ?? '') === 'super_admin');
$uid     = (int) $u['id'];
$name    = trim(((string) ($u['first_name'] ?? '')) . ' ' . ((string) ($u['last_name'] ?? '')));
?>

<div class="admin-grid-2 admin-grid-2--wide-first">
    <!-- Account -->
    <section class="admin-panel">
        <div class="admin-panel__head">
            <div class="admin-identity">
                <?= admin_avatar((string) ($u['email'] ?? ''), $name) ?>
                <div class="admin-identity__text">
                    <h2 class="admin-panel__title"><?= e((string) ($u['email'] ?? '')) ?></h2>
                    <p class="admin-panel__sub">
                        <?= $name !== '' ? e($name) . ' · ' : '' ?>Account #<?= $uid ?>
                    </p>
                </div>
            </div>
            <div class="admin-panel__actions">
                <?= admin_role_badge((string) ($u['role'] ?? 'user')) ?>
                <?= admin_badge((string) ($u['account_status'] ?? '')) ?>
            </div>
        </div>

        <div class="admin-panel__body">
            <dl class="admin-deflist mb-3">
                <div>
                    <dt>Phone</dt>
                    <dd class="admin-mono"><?= e((string) ($u['phone'] ?? '')) ?: '—' ?></dd>
                </div>
                <div>
                    <dt>Email verified</dt>
                    <dd><?= !empty($u['email_verified_at']) ? 'Yes' : 'No' ?></dd>
                </div>
                <div>
                    <dt>Location</dt>
                    <dd><?= e(trim(((string) ($u['city'] ?? '')) . ', ' . ((string) ($u['state'] ?? '')))) ?: '—' ?></dd>
                </div>
                <div>
                    <dt>Profile moderation</dt>
                    <dd><?= admin_badge((string) ($u['moderation_status'] ?? 'unknown'), ($u['moderation_status'] ?? null) ? null : 'Not tracked') ?></dd>
                </div>
                <div>
                    <dt>Last login</dt>
                    <dd title="<?= e(admin_datetime((string) ($u['last_login_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($u['last_login_at'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt>Registered</dt>
                    <dd title="<?= e(admin_datetime((string) ($u['created_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($u['created_at'] ?? '')) ?></dd>
                </div>
            </dl>

            <div class="admin-divider">Account status</div>

            <form method="POST" action="/api/admin/users/status" class="admin-field"
                  data-confirm="Apply this status change to account #<?= $uid ?>? All sessions for this user will be revoked if they are suspended."
                  data-confirm-title="Change account status">
                <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <input type="hidden" name="return_to" value="/admin/users/<?= $uid ?>">

                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="status">New status</label>
                        <?= admin_select('status', [
                            'active' => 'Active',
                            'suspended' => 'Suspended',
                            'deactivated' => 'Deactivated',
                        ], (string) ($u['account_status'] ?? 'active'), 'New status', 'w-100') ?>
                    </div>
                    <div class="col-12 col-sm-5">
                        <label class="form-label" for="statusReason">Reason <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-control" id="statusReason" name="reason" required
                               placeholder="Recorded in the audit log" maxlength="255">
                    </div>
                    <div class="col-12 col-sm-3">
                        <button class="btn btn-warning w-100" type="submit">
                            <?= admin_icon('check-circle', 15) ?> Update status
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($isSuper): ?>
                <div class="admin-divider">Role</div>
                <form method="POST" action="/api/admin/users/role" class="admin-field"
                      data-confirm="Change the role of account #<?= $uid ?>? This grants or removes privileged access immediately."
                      data-confirm-title="Change role" data-confirm-danger>
                    <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="role">Role</label>
                            <?= admin_select('role', [
                                'user' => 'User',
                                'moderator' => 'Moderator',
                                'admin' => 'Admin',
                                'super_admin' => 'Super admin',
                            ], (string) ($u['role'] ?? 'user'), 'Role', 'w-100') ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <button class="btn btn-danger w-100" type="submit">
                                <?= admin_icon('shield', 15) ?> Change role
                            </button>
                            <div class="form-text">Super admin only. The last active super admin cannot be demoted.</div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Memberships -->
    <section class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="admin-panel__title">Memberships</h2>
                <p class="admin-panel__sub">Most recent <?= count($memberships) ?> records.</p>
            </div>
        </div>
        <?php if (!$memberships): ?>
            <?= admin_empty('No memberships on this account.') ?>
        <?php else: ?>
            <div class="admin-tablewrap">
                <table class="admin-table">
                    <caption class="admin-visually-hidden">Memberships for this account</caption>
                    <thead>
                        <tr>
                            <th scope="col">Plan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Starts</th>
                            <th scope="col">Ends</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($memberships as $m): ?>
                        <tr>
                            <td class="admin-table__primary">
                                <?= e((string) ($m['plan_name_snapshot'] ?? ('#' . ($m['plan_id'] ?? '?')))) ?>
                            </td>
                            <td><?= admin_badge((string) ($m['status'] ?? '')) ?></td>
                            <td class="admin-muted admin-nowrap" title="<?= e(admin_datetime((string) ($m['starts_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($m['starts_at'] ?? '')) ?></td>
                            <td class="admin-muted admin-nowrap" title="<?= e(admin_datetime((string) ($m['ends_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($m['ends_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Purchases -->
<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Purchases</h2>
            <p class="admin-panel__sub">Amounts are snapshots taken at checkout and never change.</p>
        </div>
    </div>
    <?php if (!$purchases): ?>
        <?= admin_empty('No purchases recorded for this account.') ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table">
                <caption class="admin-visually-hidden">Purchases by this account</caption>
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Plan</th>
                        <th scope="col" class="is-numeric">Amount</th>
                        <th scope="col">Payment status</th>
                        <th scope="col">Gateway order</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td class="admin-mono"><?= e((string) ($p['public_reference'] ?? ('#' . ($p['id'] ?? '')))) ?></td>
                        <td><?= e((string) ($p['plan_name_snapshot'] ?? '')) ?></td>
                        <td class="is-numeric"><?= admin_money($p['amount_paise'] ?? 0) ?></td>
                        <td><?= admin_badge((string) ($p['payment_status'] ?? '')) ?></td>
                        <td class="admin-mono admin-muted admin-cell-narrow">
                            <?= e((string) ($p['gateway_order_id'] ?? '')) ?: '—' ?>
                        </td>
                        <td class="admin-muted admin-nowrap" title="<?= e(admin_datetime((string) ($p['created_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($p['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Audit -->
<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Audit trail</h2>
            <p class="admin-panel__sub">Actions taken by this user, and actions taken on this user.</p>
        </div>
        <div class="admin-panel__actions">
            <a class="btn btn-secondary btn-sm" href="/admin/audit?action=admin">
                <?= admin_icon('activity', 14) ?> All activity
            </a>
        </div>
    </div>
    <?php if (!$audit): ?>
        <?= admin_empty('No audit entries reference this account yet.') ?>
    <?php else: ?>
        <div class="admin-tablewrap admin-scroll-y">
            <table class="admin-table">
                <caption class="admin-visually-hidden">Audit trail for this account</caption>
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Action</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Outcome</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($audit as $a): ?>
                    <tr>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($a['created_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($a['created_at'] ?? '')) ?></td>
                        <td class="admin-mono"><?= e((string) ($a['action'] ?? '')) ?></td>
                        <td class="admin-muted"><?= e((string) ($a['actor_email'] ?? ('#' . ($a['actor_user_id'] ?? 'system')))) ?></td>
                        <td><?= admin_badge(((string) ($a['outcome'] ?? '')) === 'failure' ? 'failed' : 'success') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
