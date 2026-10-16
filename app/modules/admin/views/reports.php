<?php
/** Profile reports moderation. Expects $rows, $total, $page, $per, $status, $hasStatus, $csrf. */
$rows = is_array($rows ?? null) ? $rows : [];
$hasFilters = ((string) $status) !== '';
?>

<?php if (!$hasStatus): ?>
    <div class="admin-notice">
        <?= admin_icon('info', 18) ?>
        <div>Report status columns have not been created yet. Run the migration to enable review actions.</div>
    </div>
<?php else: ?>
    <?= admin_chips('reports', [
        '' => 'All reports',
        'open' => 'Open',
        'escalated' => 'Escalated',
        'warned' => 'Warned',
        'hidden' => 'Hidden',
        'suspended' => 'Suspended',
        'dismissed' => 'Dismissed',
    ], (string) $status) ?>
<?php endif; ?>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Reported profiles</h2>
            <p class="admin-panel__sub"><?= number_format((int) $total) ?> report<?= ((int) $total === 1 ? '' : 's') ?>.</p>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $hasFilters ? 'No reports with this status.' : 'No reports have been filed.',
            $hasFilters ? 'Choose a different status above.' : 'The queue is clear.'
        ) ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table admin-table--fixed-actions">
                <caption class="admin-visually-hidden">Reported profiles awaiting review</caption>
                <thead>
                    <tr>
                        <th scope="col" class="is-numeric">ID</th>
                        <th scope="col">Reporter</th>
                        <th scope="col">Reported profile</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Filed</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rid = (int) $r['id']; ?>
                    <tr>
                        <td class="is-numeric admin-mono admin-muted">#<?= $rid ?></td>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar((string) ($r['reporter_email'] ?? '')) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e((string) ($r['reporter_email'] ?? '')) ?></div>
                                    <div class="admin-identity__meta">User #<?= (int) ($r['reporter_id'] ?? 0) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar((string) ($r['profile_email'] ?? '')) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e((string) ($r['profile_email'] ?? '')) ?></div>
                                    <div class="admin-identity__meta">User #<?= (int) ($r['profile_id'] ?? 0) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="admin-cell-wide">
                            <span class="admin-truncate" title="<?= e((string) ($r['reason'] ?? '')) ?>">
                                <?= e(admin_truncate((string) ($r['reason'] ?? ''), 90)) ?>
                            </span>
                        </td>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($r['created_at'] ?? ''))) ?>"><?= admin_date_cell((string) ($r['created_at'] ?? '')) ?></td>
                        <td><?= admin_badge((string) ($r['status'] ?? 'open')) ?></td>
                        <td class="is-numeric">
                            <?php if (!$hasStatus): ?>
                                <span class="admin-faint">—</span>
                            <?php else: ?>
                                <div class="admin-table__actions">
                                    <form method="POST" action="/api/admin/reports/review" class="admin-rowform"
                                          data-confirm="Set report #<?= $rid ?> to the chosen status? The decision is recorded with your name and timestamp."
                                          data-confirm-title="Review report">
                                        <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                                        <input type="hidden" name="id" value="<?= $rid ?>">
                                        <?= admin_select('status', [
                                            'open' => 'Open',
                                            'escalated' => 'Escalate',
                                            'warned' => 'Warn',
                                            'hidden' => 'Hide profile',
                                            'suspended' => 'Suspend',
                                            'dismissed' => 'Dismiss',
                                        ], (string) ($r['status'] ?? 'open'), 'Status for report #' . $rid) ?>
                                        <input class="form-control form-control-sm" name="note" type="text"
                                               maxlength="500" placeholder="Note (optional)"
                                               aria-label="Review note for report <?= $rid ?>"
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

        <?= admin_pagination((int) $page, (int) $total, (int) $per, ['__route' => 'reports'], 'reports') ?>
    <?php endif; ?>
</section>
