<?php
/** Admin accounts (super admin only). Expects $rows, $csrf, $me. */
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="admin-grid-2 admin-grid-2--wide-first">
    <section class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="admin-panel__title">Privileged accounts</h2>
                <p class="admin-panel__sub"><?= count($rows) ?> account<?= (count($rows) === 1 ? '' : 's') ?> with staff access.</p>
            </div>
        </div>

        <?php if (!$rows): ?>
            <?= admin_empty('No staff accounts found.') ?>
        <?php else: ?>
            <div class="admin-tablewrap">
                <table class="admin-table admin-table--fixed-actions">
                    <caption class="admin-visually-hidden">Accounts with elevated privileges</caption>
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last login</th>
                            <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $a): ?>
                        <?php $aid = (int) $a['id']; $isMe = $aid === (int) $me; ?>
                        <tr>
                            <td>
                                <div class="admin-identity">
                                    <?= admin_avatar((string) ($a['email'] ?? '')) ?>
                                    <div class="admin-identity__text">
                                        <div class="admin-identity__name"><?= e((string) ($a['email'] ?? '')) ?></div>
                                        <div class="admin-identity__meta">
                                            ID #<?= $aid ?> · joined <?= admin_date_cell((string) ($a['created_at'] ?? '')) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= admin_role_badge((string) ($a['role'] ?? '')) ?></td>
                            <td><?= admin_badge((string) ($a['account_status'] ?? '')) ?></td>
                            <td class="admin-nowrap admin-muted"><?= admin_date_cell((string) ($a['last_login_at'] ?? '')) ?></td>
                            <td class="is-numeric">
                                <div class="admin-table__actions">
                                    <?php if ($isMe): ?>
                                        <span class="admin-badge admin-badge--muted">This is you</span>
                                    <?php else: ?>
                                        <form method="POST" action="/api/admin/users/role" class="admin-rowform"
                                              data-confirm="Change <?= e((string) ($a['email'] ?? '')) ?> to the selected role? Privileged access changes immediately."
                                              data-confirm-title="Change role" data-confirm-danger>
                                            <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                                            <input type="hidden" name="user_id" value="<?= $aid ?>">
                                            <input type="hidden" name="return_to" value="/admin/admins">
                                            <?= admin_select('role', [
                                                'user' => 'User',
                                                'moderator' => 'Moderator',
                                                'admin' => 'Admin',
                                                'super_admin' => 'Super admin',
                                            ], (string) ($a['role'] ?? 'user'), 'New role for ' . ($a['email'] ?? 'this account')) ?>
                                            <button class="btn btn-secondary btn-sm" type="submit">Set</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="admin-panel__title">Create an admin account</h2>
                <p class="admin-panel__sub">The new account is active and email-verified immediately.</p>
            </div>
        </div>
        <form method="POST" action="/api/admin/users/create" class="admin-panel__body"
              data-confirm="Create a new staff account? It will have full access to the admin panel immediately."
              data-confirm-title="Create admin account" data-confirm-danger data-confirm-text="Create account">
            <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">

            <div class="admin-field mb-3">
                <label class="form-label" for="newAdminEmail">Email address <span class="req" aria-hidden="true">*</span></label>
                <input class="form-control" id="newAdminEmail" type="email" name="email" required
                       autocomplete="off" placeholder="staff@example.com">
            </div>

            <div class="admin-field mb-3">
                <label class="form-label" for="newAdminPassword">Password <span class="req" aria-hidden="true">*</span></label>
                <input class="form-control" id="newAdminPassword" type="password" name="password" required
                       minlength="10" autocomplete="new-password" placeholder="At least 10 characters">
                <div class="form-text">Minimum 10 characters. The admin must re-confirm before any high-risk action.</div>
            </div>

            <div class="admin-field mb-4">
                <label class="form-label" for="newAdminRole">Role <span class="req" aria-hidden="true">*</span></label>
                <?= admin_select('role', [
                    'admin' => 'Admin — full access except role management',
                    'moderator' => 'Moderator — profiles and reports only',
                    'super_admin' => 'Super admin — full access',
                ], 'admin', 'Role for the new account', 'w-100') ?>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">
                    <?= admin_icon('plus', 15) ?> Create admin
                </button>
            </div>
        </form>
    </section>
</div>

<div class="admin-notice" style="margin-top:1.15rem">
    <?= admin_icon('shield', 18) ?>
    <div>
        The last active super admin cannot be demoted — this prevents the panel from being locked out.
        Every role change here is written to the audit log with before and after values.
    </div>
</div>
