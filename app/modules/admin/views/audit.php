<?php
/** Audit log. Expects $rows, $page, $per, $total, $action, $outcome, $mine. */
$rows     = is_array($rows ?? null) ? $rows : [];
$action   = (string) $action;
$outcome  = (string) $outcome;
$mine     = (string) $mine;
$hasFilter = $action !== '' || $outcome !== '' || $mine === '1';
?>

<form class="admin-toolbar" method="GET" action="/admin/audit" role="search">
    <input type="hidden" name="page" value="1">

    <div class="admin-toolbar__search">
        <label class="admin-visually-hidden" for="auditAction">Filter audit actions</label>
        <?= admin_icon('search', 16) ?>
        <input id="auditAction" type="search" name="action" value="<?= e($action) ?>"
               placeholder="Action, e.g. admin.user.status" autocomplete="off" data-search-submit>
    </div>

    <div class="admin-field">
        <label class="admin-visually-hidden" for="auditOutcome">Filter by outcome</label>
        <?= admin_select('outcome', [
            '' => 'All outcomes',
            'success' => 'Success',
            'failure' => 'Failure',
        ], $outcome, 'Filter by outcome', 'admin-filter-select') ?>
    </div>

    <div class="admin-field form-check ms-1">
        <input class="form-check-input" type="checkbox" id="auditMine" name="mine" value="1"
               <?= $mine === '1' ? 'checked' : '' ?> data-autosubmit>
        <label class="form-check-label" for="auditMine">Only my actions</label>
    </div>

    <button class="btn btn-primary btn-sm" type="submit"><?= admin_icon('filter', 14) ?> Apply</button>
    <?php if ($hasFilter): ?>
        <button class="btn btn-ghost btn-sm" type="button" data-filter-reset>Clear</button>
    <?php endif; ?>
</form>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Audit entries</h2>
            <p class="admin-panel__sub"><?= number_format((int) $total) ?> entr<?= ((int) $total === 1 ? 'y' : 'ies') ?>.</p>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $hasFilter ? 'No entries match these filters.' : 'The audit log is empty.',
            $hasFilter ? 'Try a different action name or clear the filters.' : ''
        ) ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table">
                <caption class="admin-visually-hidden">Audit log entries</caption>
                <thead>
                    <tr>
                        <th scope="col" class="is-numeric">ID</th>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Action</th>
                        <th scope="col">Entity</th>
                        <th scope="col">Outcome</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $a): ?>
                    <?php $isFailure = ((string) ($a['outcome'] ?? '')) === 'failure'; ?>
                    <tr>
                        <td class="is-numeric admin-mono admin-muted"><?= (int) ($a['id'] ?? 0) ?></td>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($a['created_at'] ?? ''))) ?>">
                            <?= admin_date_cell((string) ($a['created_at'] ?? '')) ?>
                            <div class="admin-table__sub"><?= e(admin_when((string) ($a['created_at'] ?? ''))) ?></div>
                        </td>
                        <td>
                            <div class="admin-identity">
                                <?php if (!empty($a['email'])): ?>
                                    <?= admin_avatar((string) $a['email']) ?>
                                    <div class="admin-identity__text">
                                        <div class="admin-identity__name"><?= e((string) $a['email']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="admin-avatar admin-avatar--system" aria-hidden="true">
                                        <?= admin_icon('shield', 15) ?>
                                    </span>
                                    <div class="admin-identity__text">
                                        <div class="admin-identity__name admin-muted">System</div>
                                        <div class="admin-identity__meta">no signed-in actor</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="admin-mono"><?= e((string) ($a['action'] ?? '')) ?></td>
                        <td class="admin-muted">
                            <?php $entity = trim(((string) ($a['entity_type'] ?? '')) . ' ' . ((string) ($a['entity_id'] ?? ''))); ?>
                            <?= $entity !== '' ? e($entity) : '<span class="admin-faint">—</span>' ?>
                        </td>
                        <td>
                            <?= admin_badge($isFailure ? 'failed' : 'success') ?>
                        </td>
                        <td class="admin-mono admin-muted admin-nowrap"><?= e((string) ($a['ip_address'] ?? '')) ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_pagination((int) $page, (int) $total, (int) $per, ['__route' => 'audit'], 'entries') ?>
    <?php endif; ?>
</section>

<div class="admin-notice" style="margin-top:1.15rem">
    <?= admin_icon('lock', 18) ?>
    <div>The audit log is <strong>append-only</strong>. No delete or edit UI exists by design, and entries cannot be modified through the API.</div>
</div>
