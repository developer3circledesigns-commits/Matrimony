<?php
/** User directory. Expects $list, $f, $counts, $isSuper, $csrf. */
$rows  = is_array($list['data'] ?? null) ? $list['data'] : [];
$total = (int) ($list['total'] ?? 0);
$page  = (int) ($list['page'] ?? 1);
$per   = (int) ($list['per'] ?? 25);
$counts = is_array($counts ?? null) ? $counts : [];
$f     = is_array($f ?? null) ? $f : [];
$q     = (string) ($f['q'] ?? '');
$role  = (string) ($f['role'] ?? '');
$stat  = (string) ($f['status'] ?? '');
$verified = (string) ($f['verified'] ?? '');
$hasProfile = (string) ($f['has_profile'] ?? '');
$sort  = (string) ($f['sort'] ?? 'newest');
$hasFilters = $q !== '' || $role !== '' || $stat !== '' || $verified !== '' || $hasProfile !== '';

$statusOptions = ['' => 'All statuses'];
foreach ([
    'pending_verification' => 'Unverified',
    'active' => 'Active',
    'suspended' => 'Suspended',
    'deactivated' => 'Deactivated',
    'deleted' => 'Deleted',
] as $k => $label) {
    $statusOptions[$k] = isset($counts[$k]) ? $label . ' (' . number_format($counts[$k]) . ')' : $label;
}
?>

<form class="admin-toolbar" method="GET" action="/admin/users" role="search">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">

    <div class="admin-toolbar__search">
        <label class="admin-visually-hidden" for="userSearch">Search users</label>
        <?= admin_icon('search', 16) ?>
        <input id="userSearch" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Email, name, city or phone…" autocomplete="off" data-search-submit>
    </div>

    <div class="admin-field">
        <label class="admin-visually-hidden" for="role">Filter by role</label>
        <?= admin_select('role', [
            '' => 'All roles',
            'user' => 'User',
            'moderator' => 'Moderator',
            'admin' => 'Admin',
            'super_admin' => 'Super admin',
        ], $role, 'Filter by role', 'admin-filter-select') ?>
    </div>

    <div class="admin-field">
        <label class="admin-visually-hidden" for="status">Filter by status</label>
        <?= admin_select('status', $statusOptions, $stat, 'Filter by status', 'admin-filter-select') ?>
    </div>

    <div class="admin-field">
        <label class="admin-visually-hidden" for="sort">Sort order</label>
        <?= admin_select('sort', [
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'name' => 'Name A–Z',
            'email' => 'Email A–Z',
            'status' => 'Status',
            'last_login' => 'Recently active',
        ], $sort, 'Sort order', 'admin-filter-select') ?>
    </div>

    <div class="admin-field form-check ms-1">
        <input class="form-check-input" type="checkbox" id="userVerified" name="verified" value="1"
               <?= $verified === '1' ? 'checked' : '' ?> data-autosubmit>
        <label class="form-check-label" for="userVerified">Verified only</label>
    </div>

    <div class="admin-field form-check ms-1">
        <input class="form-check-input" type="checkbox" id="userNoProfile" name="has_profile" value="0"
               <?= $hasProfile === '0' ? 'checked' : '' ?> data-autosubmit>
        <label class="form-check-label" for="userNoProfile">Missing profile</label>
    </div>

    <button class="btn btn-primary btn-sm" type="submit"><?= admin_icon('filter', 14) ?> Apply</button>

    <?php if ($hasFilters): ?>
        <button class="btn btn-ghost btn-sm" type="button" data-filter-reset>Clear</button>
    <?php endif; ?>
</form>

<section class="admin-panel">
    <div class="admin-panel__head">
        <div>
            <h2 class="admin-panel__title">Accounts</h2>
            <p class="admin-panel__sub">
                <?= $hasFilters ? 'Filtered results' : 'All accounts' ?> ·
                <?= number_format($total) ?> total
            </p>
        </div>
        <div class="admin-panel__actions">
            <a class="btn btn-secondary btn-sm" href="/admin/users/create">
                <?= admin_icon('plus', 14) ?> New user
            </a>
        </div>
    </div>

    <?php if (!$rows): ?>
        <?= admin_empty(
            $hasFilters ? 'No accounts match these filters.' : 'No accounts yet.',
            $hasFilters ? 'Try widening the search or clearing the filters.' : 'Create the first account to get started.'
        ) ?>
        <?php if (!$hasFilters): ?>
            <div class="admin-panel__foot d-flex justify-content-center">
                <a class="btn btn-primary" href="/admin/users/create">
                    <?= admin_icon('plus', 15) ?> Create user
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="admin-tablewrap">
            <table class="admin-table admin-table--fixed-actions">
                <caption class="admin-visually-hidden">User accounts</caption>
                <thead>
                    <tr>
                        <?= admin_sort_link('email', 'Member', $sort === 'email' ? 'email' : '') ?>
                        <th scope="col">Role</th>
                        <?= admin_sort_link('status', 'Status', $sort === 'status' ? 'status' : '') ?>
                        <th scope="col">Verified</th>
                        <?= admin_sort_link('last_login', 'Last login', $sort === 'last_login' ? 'last_login' : '') ?>
                        <?= admin_sort_link('name', 'Joined', $sort === 'name' ? 'name' : '') ?>
                        <th scope="col"><span class="admin-visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $u): ?>
                    <?php
                    $id = (int) $u['id'];
                    $displayName = trim(((string) ($u['first_name'] ?? '')) . ' ' . ((string) ($u['last_name'] ?? '')));
                    $email = (string) ($u['email'] ?? '');
                    $deleted = (string) ($u['account_status'] ?? '') === 'deleted';
                    $phone = trim((string) ($u['phone'] ?? ''));
                    ?>
                    <tr<?= $deleted ? ' class="admin-row--deleted"' : '' ?>>
                        <td>
                            <div class="admin-identity">
                                <?= admin_avatar($email, $displayName) ?>
                                <div class="admin-identity__text">
                                    <div class="admin-identity__name"><?= e($email) ?></div>
                                    <div class="admin-identity__meta">
                                        <?= $displayName !== '' ? e($displayName) . ' · ' : '' ?>#<?= $id ?>
                                        <?= $phone !== '' ? ' · ' . e($phone) : '' ?>
                                        <?= empty($u['profile_id']) ? ' · <span class="admin-badge admin-badge--muted">No profile</span>' : '' ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?= admin_role_badge((string) ($u['role'] ?? 'user')) ?></td>
                        <td><?= admin_badge((string) ($u['account_status'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($u['is_verified'])): ?>
                                <span class="admin-badge admin-badge--success">Verified</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge--muted">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($u['last_login_at'] ?? ''))) ?>">
                            <?= admin_date_cell((string) ($u['last_login_at'] ?? '')) ?>
                        </td>
                        <td class="admin-nowrap admin-muted" title="<?= e(admin_datetime((string) ($u['created_at'] ?? ''))) ?>">
                            <?= admin_date_cell((string) ($u['created_at'] ?? '')) ?>
                        </td>
                        <td class="is-numeric">
                            <div class="admin-table__actions">
                                <a class="btn btn-secondary btn-sm" href="/admin/users/<?= $id ?>">
                                    <?= admin_icon('external', 13) ?> View
                                </a>
                                <?php if (!empty($isSuper) || !in_array((string) ($u['role'] ?? 'user'), ['admin', 'super_admin'], true)): ?>
                                    <a class="btn btn-ghost btn-sm" href="/admin/users/<?= $id ?>/edit"
                                       aria-label="Edit <?= e($email) ?>" title="Edit">
                                        <?= admin_icon('edit', 13) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="admin-faint" title="Only a Super Admin can edit this account">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= admin_pagination($page, $total, $per, ['__route' => 'users'], 'accounts') ?>
    <?php endif; ?>
</section>
