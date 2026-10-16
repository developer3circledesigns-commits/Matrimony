<?php
declare(strict_types=1);
/**
 * Admin user CRUD.
 *
 *   POST /api/admin/users/create            create an account + profile
 *   POST /api/admin/users/update            update account + profile
 *   POST /api/admin/users/delete            soft delete (needs reason)
 *   POST /api/admin/users/restore           undo a soft delete
 *   POST /api/admin/users/password          set / reset a password
 *   POST /api/admin/users/status            quick status change (legacy)
 *   POST /api/admin/users/role              quick role change  (legacy)
 *   POST /api/admin/users/create-admin      staff-only quick create (legacy)
 *   POST /api/admin/users/check-email       check email uniqueness (AJAX)
 *
 * HTML form submissions get a redirect + flash (Post/Redirect/Get); XHR
 * callers still receive JSON.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\AdminUserService;

admin_json_header();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
if ($isJson) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    foreach ($in as $k => $v) {
        $_POST[$k] = $v;
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_respond(['success' => false, 'error' => 'POST required'], '/admin/users', 405);
}

Csrf::require();
$admin = Authorization::requireAdmin();
$adminId = (int) $admin['id'];
$isSuper = $admin['role'] === 'super_admin';

/** Bounce back to a form with per-field errors and the operator's input intact. */
$failWithErrors = static function (string $formUrl, array $errors, array $old): void {
    if (admin_wants_json()) {
        admin_respond(['success' => false, 'error' => (string) reset($errors), 'errors' => $errors], $formUrl, 422);
    }
    flash_form($errors, $old);
    flash('error', (string) reset($errors));
    header('Location: ' . $formUrl, true, 302);
    exit;
};

$userId = (int) ($_POST['user_id'] ?? $_POST['id'] ?? 0);

/* ---------------------------------------------------- check-email (AJAX) ---- */

if (str_ends_with($path, '/check-email')) {
    Csrf::require();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $exceptId = (int) ($_POST['user_id'] ?? 0);

    if ($email === '') {
        admin_respond(['success' => false, 'exists' => false], '', 400);
    }

    $exists = AdminUserService::emailExists($email, $exceptId > 0 ? $exceptId : null);
    admin_respond(['success' => true, 'exists' => $exists], '', 200);
}

/* ------------------------------------------------------------ create ---- */

if (str_ends_with($path, '/create') || str_ends_with($path, '/create-admin')) {
    Authorization::requireReauth();

    $requestedRole = (string) ($_POST['role'] ?? 'user');
    $staffOnly = str_ends_with($path, '/create-admin');

    if ($staffOnly && !in_array($requestedRole, ['admin', 'moderator', 'super_admin'], true)) {
        $requestedRole = 'admin';
    }
    if (!$isSuper && in_array($requestedRole, AdminUserService::PRIVILEGED_ROLES, true)) {
        $failWithErrors('/admin/users/create', ['role' => 'Only a Super Admin can create admin accounts.'], $_POST);
    }
    // The staff quick-create form only sends email/password/role; the full
    // account form is validated by create() below.
    if ($staffOnly) {
        $res = AdminUserService::create($adminId, $isSuper, [
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'role' => $requestedRole,
            'account_status' => 'active',
            'is_verified' => '1',
            'first_name' => $_POST['first_name'] ?? (string) strstr((string) ($_POST['email'] ?? ''), '@', true),
            'gender' => $_POST['gender'] ?? 'other',
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
        ]);
    } else {
        $res = AdminUserService::create($adminId, $isSuper, $_POST, $_FILES);
    }

    if (empty($res['success'])) {
        $formUrl = $staffOnly ? '/admin/admins' : '/admin/users/create';
        $failWithErrors($formUrl, $res['errors'] ?? ['email' => (string) ($res['error'] ?? 'Could not create the account.')], $_POST);
    }

    $name = trim((string) ($_POST['first_name'] ?? '') . ' ' . (string) ($_POST['last_name'] ?? ''));
    admin_respond([
        'success' => true,
        'id' => $res['id'],
        'message' => 'Created ' . str_replace('_', ' ', $requestedRole) . ' account'
            . ($name !== '' ? ' for ' . $name : '') . '.'
            . (isset($res['warning']) ? ' ' . $res['warning'] : ''),
    ], $staffOnly ? '/admin/admins' : '/admin/users/' . (int) $res['id']);
}

/* ------------------------------------------------------------ update ---- */

if (str_ends_with($path, '/update')) {
    Authorization::requireReauth();

    if ($userId <= 0) {
        $failWithErrors('/admin/users', ['user_id' => 'Which user?'], $_POST);
    }
    $back = admin_return_to('/admin/users/' . $userId, '/admin/users/' . $userId);

    $res = AdminUserService::update($adminId, $isSuper, $userId, $_POST, $_FILES);
    if (empty($res['success'])) {
        $failWithErrors('/admin/users/' . $userId . '/edit', $res['errors'] ?? ['email' => (string) ($res['error'] ?? 'Could not save.')], $_POST);
    }

    $changed = (array) ($res['changed'] ?? []);
    $summary = $changed === [] ? 'no field changes' : count($changed) . ' field' . (count($changed) === 1 ? '' : 's') . ' updated';
    admin_respond([
        'success' => true,
        'id' => $userId,
        'changed' => $changed,
        'message' => 'Account saved (' . $summary . ').'
            . (isset($res['warning']) ? ' ' . $res['warning'] : ''),
    ], $back);
}

/* ------------------------------------------------------------ delete ---- */

if (str_ends_with($path, '/delete')) {
    Authorization::requireReauth();

    $reason = trim((string) ($_POST['reason'] ?? ''));
    $res = AdminUserService::delete($adminId, $userId, $reason);

    if (empty($res['success'])) {
        $failWithErrors(admin_return_to('/admin/users/' . $userId), ['reason' => (string) $res['error']], $_POST);
    }

    // An empty reason reaching here means delete() already vetted it.
    admin_respond([
        'success' => true,
        'message' => 'Account deleted. Sessions were revoked, the profile was hidden and active memberships suspended.',
    ], admin_return_to('/admin/users', '/admin/users'));
}

/* ----------------------------------------------------------- restore ---- */

if (str_ends_with($path, '/restore')) {
    Authorization::requireReauth();

    $res = AdminUserService::restore($adminId, $userId);
    if (empty($res['success'])) {
        admin_respond([
            'success' => false,
            'error' => (string) $res['error'],
            'message' => 'Could not restore: ' . (string) $res['error'],
        ], admin_return_to('/admin/users/' . $userId), 422);
    }

    admin_respond([
        'success' => true,
        'message' => 'Account restored to active. The member can sign in again.',
    ], admin_return_to('/admin/users/' . $userId));
}

/* ---------------------------------------------------------- password ---- */

if (str_ends_with($path, '/password')) {
    Authorization::requireReauth();

    $password = (string) ($_POST['password'] ?? '');
    $mustChange = !empty($_POST['must_change']);
    $back = admin_return_to('/admin/users/' . $userId, '/admin/users/' . $userId);

    $res = AdminUserService::setPassword($adminId, $userId, $password, $mustChange);
    if (empty($res['success'])) {
        $failWithErrors($back . '/edit#password', $res['errors'] ?? ['password' => (string) $res['error']], $_POST);
    }

    admin_respond([
        'success' => true,
        'message' => 'Password set and all sessions revoked.'
            . ($mustChange ? ' The member must change it at next sign-in.' : ''),
    ], $back);
}

/* ------------------------------------------------- status / role (legacy) */

if (str_ends_with($path, '/status')) {
    Authorization::requireReauth();
    $res = AdminUserService::setStatus(
        $adminId,
        $isSuper,
        $userId,
        (string) ($_POST['status'] ?? ''),
        (string) ($_POST['reason'] ?? '')
    );
    $redirect = admin_return_to('/admin/users/' . $userId);
    if (!empty($res['success'])) {
        $res['message'] = 'Account status updated to ' . (string) ($_POST['status'] ?? '') . '.';
    } else {
        $res['message'] = 'Status not changed: ' . (string) ($res['error'] ?? 'unknown error');
    }
    admin_respond($res, $redirect, !empty($res['success']) ? 200 : 422);
}

if (str_ends_with($path, '/role')) {
    Authorization::requireRole(['super_admin']);
    Authorization::requireReauth();
    $role = (string) ($_POST['role'] ?? '');
    $res = AdminUserService::setRole($adminId, $userId, $role);
    if (!empty($res['success'])) {
        $res['message'] = 'Role updated to ' . str_replace('_', ' ', $role) . '.';
    } else {
        $res['message'] = 'Role not changed: ' . (string) ($res['error'] ?? 'unknown error');
    }
    admin_respond($res, admin_return_to('/admin/users/' . $userId), !empty($res['success']) ? 200 : 422);
}

admin_respond(['success' => false, 'error' => 'Not found', 'message' => 'Unknown user endpoint.'], '/admin/users', 404);
