<?php
declare(strict_types=1);
// POST /api/admin/plans/update
require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Database\Connection;
use Matrimony\Security\AuditService;

admin_json_header();
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    foreach ($in as $k => $v) {
        $_POST[$k] = $v;
    }
}
Csrf::require();
$admin = Authorization::requireSuperAdmin();
Authorization::requireReauth();

$fallback = admin_return_to('/admin/plans');

$id = (int) ($_POST['id'] ?? 0);
$planName = trim((string) ($_POST['plan_name'] ?? ''));
$priceInr = (int) ($_POST['price_inr'] ?? -1);
$duration = (int) ($_POST['duration_days'] ?? 0);
$limitRaw = trim((string) ($_POST['contact_limit'] ?? ''));
$unlimited = !empty($_POST['is_unlimited']);
$active = !empty($_POST['is_active']);
$order = (int) ($_POST['display_order'] ?? 0);
$desc = substr(trim((string) ($_POST['description'] ?? '')), 0, 1000);

if ($id <= 0 || $priceInr < 1 || $duration < 1 || $duration > 3650) {
    admin_respond([
        'success' => false,
        'error' => 'Invalid plan values',
        'message' => 'Not saved: a plan id, a price of at least ₹1 and a duration of 1–3650 days are required.',
    ], $fallback, 400);
}
$limit = null;
if (!$unlimited) {
    if ($limitRaw === '') {
        admin_respond([
            'success' => false,
            'error' => 'Contact limit is required unless Unlimited is checked',
            'message' => 'Not saved: set a contact limit or tick "Unlimited contacts".',
        ], $fallback, 400);
    }
    $limit = (int) $limitRaw;
    if ($limit < 1 || $limit > 100000) {
        admin_respond([
            'success' => false,
            'error' => 'Invalid contact limit',
            'message' => 'Not saved: the contact limit must be between 1 and 100000.',
        ], $fallback, 400);
    }
}
$pdo = Connection::pdo();
$s = $pdo->prepare('SELECT * FROM membership_plans WHERE id = :id LIMIT 1');
$s->execute([':id' => $id]);
$before = $s->fetch();
if (!$before) {
    admin_respond([
        'success' => false,
        'error' => 'Plan not found',
        'message' => 'Not saved: that plan no longer exists.',
    ], $fallback, 404);
}
$paise = $priceInr * 100;
$pdo->prepare('UPDATE membership_plans SET price_paise = :pp1, price_cents = :pp2, contact_limit = :lim, is_unlimited = :u, duration_days = :d, is_active = :a, display_order = :o, description = :de, updated_by = :ub WHERE id = :id')
    ->execute([':pp1' => $paise, ':pp2' => $paise, ':lim' => $limit, ':u' => $unlimited ? 1 : 0, ':d' => $duration, ':a' => $active ? 1 : 0, ':o' => $order, ':de' => $desc, ':ub' => $admin['id'], ':id' => $id]);
AuditService::log((int) $admin['id'], 'admin.plan.update', 'plan', $id, [
    'before_price' => $before['price_paise'],
    'after_price' => $paise,
    'before_limit' => $before['contact_limit'],
    'after_limit' => $limit,
]);
// Existing purchases keep their snapshots — nothing else to update.
$label = $planName !== '' ? '"' . $planName . '"' : '#' . $id;
admin_respond([
    'success' => true,
    'message' => 'Plan ' . $label . ' saved. Existing purchases keep their original snapshots.',
], $fallback);
