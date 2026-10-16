<?php
/** Email outbox. Expects $rows, $page, $per, $total, $status, $csrf. */
$rows = is_array($rows ?? null) ? $rows : [];
?>

<?= admin_chips('outbox', [
    '' => 'All',
    'pending' => 'Pending',
    'queued' => 'Queued',
    'sent' => 'Sent',
    'failed' => 'Failed',
], (string) $status) ?>

<div class="admin-pagehead" style="margin-bottom:1rem">
    <div class="admin-pagehead__text">
        <h2 style="font-size:1rem">Delivery processing</h2>
        <p class="admin-pagehead__sub">Queued email is flushed on a schedule. Trigger a batch manually after fixing a delivery issue.</p>
    </div>
    <div class="admin-pagehead__actions">
        <form method="POST" action="/api/admin/outbox/retry"
              data-confirm="Process up to 25 due emails now? This sends real messages to members."
              data-confirm-title="Process email batch" data-confirm-text="Send now">
            <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
            <button class="btn btn-primary btn-sm" type="submit">
                <?= admin_icon('send', 14) ?> Process due emails
            </button>
        </form>
    </div>
</div>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Outbox</h2>
            <p class="admin-panel__sub"><?= number_format((int) $total) ?> message<?= ((int) $total === 1 ? '' : 's') ?>.</p>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $status !== '' ? 'No ' . $status . ' messages.' : 'The outbox is empty.',
            $status !== '' ? 'Choose another status above.' : 'Transactional email will appear here.'
        ) ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table admin-table--fixed-actions">
                <caption class="admin-visually-hidden">Queued transactional email</caption>
                <thead>
                    <tr>
                        <th scope="col" class="is-numeric">ID</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Template</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="is-numeric">Attempts</th>
                        <th scope="col">Last error</th>
                        <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rid = (int) $r['id']; $failed = ((string) ($r['status'] ?? '')) === 'failed'; ?>
                    <tr>
                        <td class="is-numeric admin-mono admin-muted">#<?= $rid ?></td>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar((string) ($r['recipient_email'] ?? '')) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e((string) ($r['recipient_email'] ?? '')) ?></div>
                                    <div class="admin-identity__meta"><?= admin_date_cell((string) ($r['created_at'] ?? '')) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="admin-mono admin-muted"><?= e((string) ($r['template_key'] ?? '')) ?></td>
                        <td class="admin-cell-wide">
                            <span class="admin-truncate" title="<?= e((string) ($r['subject'] ?? '')) ?>">
                                <?= e(admin_truncate((string) ($r['subject'] ?? ''), 60)) ?>
                            </span>
                        </td>
                        <td><?= admin_badge((string) ($r['status'] ?? '')) ?></td>
                        <td class="is-numeric"><?= (int) ($r['attempts'] ?? 0) ?></td>
                        <td class="admin-muted admin-cell-narrow">
                            <?php if (trim((string) ($r['last_error'] ?? '')) !== ''): ?>
                                <span class="admin-truncate" style="color:var(--adm-danger)"
                                      title="<?= e((string) $r['last_error']) ?>">
                                    <?= e(admin_truncate((string) $r['last_error'], 70)) ?>
                                </span>
                            <?php else: ?>
                                <span class="admin-faint">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="is-numeric">
                            <div class="admin-table__actions">
                                <?php if ($failed): ?>
                                    <form method="POST" action="/api/admin/outbox/retry" class="admin-rowform"
                                          data-confirm="Retry delivery for message #<?= $rid ?> to <?= e((string) ($r['recipient_email'] ?? '')) ?>?"
                                          data-confirm-title="Retry delivery" data-confirm-text="Retry">
                                        <input type="hidden" name="csrf" value="<?= e((string) $csrf) ?>">
                                        <input type="hidden" name="message_id" value="<?= $rid ?>">
                                        <button class="btn btn-secondary btn-sm" type="submit">
                                            <?= admin_icon('refresh', 13) ?> Retry
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="admin-faint">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_pagination((int) $page, (int) $total, (int) $per, ['__route' => 'outbox'], 'messages') ?>
    <?php endif; ?>
</section>
