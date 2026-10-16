<?php
/** Profile moderation. Expects $rows, $total, $page, $per, $q, $mod, $hasMod, $csrf. */
$hasFilters = ($q ?? '') !== '' || ($mod ?? '') !== '';
?>

<?php if (!$hasMod): ?>
    <div class="admin-notice">
        <?= admin_icon('info', 18) ?>
        <div>Moderation columns have not been created yet. Run the migration to enable approve / hide / flag actions.</div>
    </div>
<?php endif; ?>

<form class="admin-toolbar" method="GET" action="/admin/profiles" role="search">
    <input type="hidden" name="page" value="1">

    <div class="admin-toolbar__search">
        <label class="admin-visually-hidden" for="profileSearch">Search profiles</label>
        <?= admin_icon('search', 16) ?>
        <input id="profileSearch" type="search" name="q" value="<?= e((string) $q) ?>"
               placeholder="Name or email…" autocomplete="off" data-search-submit>
    </div>

    <div class="admin-field">
        <label class="admin-visually-hidden" for="profileMod">Filter by moderation status</label>
        <?= admin_select('mod', [
            '' => 'All moderation states',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'flagged' => 'Flagged',
            'hidden' => 'Hidden',
            'rejected' => 'Rejected',
        ], (string) $mod, 'Filter by moderation status', 'admin-filter-select') ?>
    </div>

    <button class="btn btn-primary btn-sm" type="submit"><?= admin_icon('filter', 14) ?> Apply</button>
    <?php if ($hasFilters): ?>
        <button class="btn btn-ghost btn-sm" type="button" data-filter-reset>Clear</button>
    <?php endif; ?>
</form>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Profiles</h2>
            <p class="admin-panel__sub"><?= number_format((int) $total) ?> matching profile<?= ((int) $total === 1 ? '' : 's') ?>.</p>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $hasFilters ? 'No profiles match these filters.' : 'No profiles yet.',
            $hasFilters ? 'Try a different search term or clear the filters.' : ''
        ) ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table admin-table--fixed-actions">
                <caption class="admin-visually-hidden">Member profiles and moderation state</caption>
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Name</th>
                        <th scope="col">Gender</th>
                        <th scope="col">Location</th>
                        <th scope="col">Moderation</th>
                        <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $uid = (int) $r['user_id']; ?>
                    <tr>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar((string) ($r['email'] ?? '')) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e((string) ($r['email'] ?? '')) ?></div>
                                    <div class="admin-identity__meta">ID #<?= $uid ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="admin-table__primary"><?= e(trim(((string) ($r['first_name'] ?? '')) . ' ' . ((string) ($r['last_name'] ?? '')))) ?></td>
                        <td class="admin-muted"><?= e((string) ($r['gender'] ?? '')) ?: '—' ?></td>
                        <td class="admin-muted"><?= e(trim(((string) ($r['city'] ?? '')) . ', ' . ((string) ($r['state'] ?? '')))) ?: '—' ?></td>
                        <td><?= admin_badge((string) ($r['moderation_status'] ?? '')) ?></td>
                        <td class="is-numeric">
                            <?php if (!$hasMod): ?>
                                <span class="admin-faint">—</span>
                            <?php else: ?>
                                <div class="admin-table__actions">
                                    <form method="POST" action="/api/admin/profiles/moderate" class="admin-rowform">
                                        <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <?= admin_select('status', [
                                            'approved' => 'Approve',
                                            'pending' => 'Pending',
                                            'flagged' => 'Flag',
                                            'hidden' => 'Hide',
                                            'rejected' => 'Reject',
                                        ], 'approved', 'Set moderation status for ' . ($r['email'] ?? 'this profile')) ?>
                                        <input class="form-control form-control-sm" name="reason" type="text"
                                               maxlength="255" placeholder="Reason (optional)"
                                               aria-label="Moderation reason for <?= e((string) ($r['email'] ?? 'this profile')) ?>"
                                               style="width:11rem">
                                        <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_pagination((int) $page, (int) $total, (int) $per, ['__route' => 'profiles'], 'profiles') ?>
    <?php endif; ?>
</section>

<div class="admin-notice" style="margin-top:1.15rem">
    <?= admin_icon('info', 18) ?>
    <div>
        Hiding or rejecting a profile sets <strong>is_hidden = 1</strong> immediately and emails the member.
        Approving reverses it. Every change is written to the audit log with its reason.
    </div>
</div>
