<?php
declare(strict_types=1);
// Usage: php app/database/create-admin.php --email=admin@example.com --password='Strong#123' [--role=super_admin]
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Database\Connection;

$opts = getopt('', ['email:', 'password:', 'role::']);
$email = strtolower(trim($opts['email'] ?? ''));
$password = $opts['password'] ?? '';
$role = $opts['role'] ?? 'super_admin';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    exit("Usage: php app/database/create-admin.php --email=admin@example.com --password='StrongPass#123' [--role=super_admin]\nPassword must be 10+ chars.\n");
}
if (!in_array($role, ['super_admin', 'admin', 'moderator'], true)) {
    exit("Invalid role\n");
}
$pdo = Connection::pdo();
$stmt = $pdo->prepare('SELECT id, role FROM users WHERE email_normalized = :e OR email = :e2 LIMIT 1');
$stmt->execute([':e' => $email, ':e2' => $email]);
$existing = $stmt->fetch();
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($existing) {
    // Promote only with explicit super_admin presence check
    $supers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND account_status='active'")->fetchColumn();
    if ($role === 'super_admin' || $existing['role'] !== 'user') {
        // Require existing super admin unless none exists (first bootstrap)
        if ($supers > 0) {
            echo "Refusing: promoting/changing privileged accounts must be done by a super_admin in the admin panel.\n";
            exit(1);
        }
    }
    $pdo->prepare('UPDATE users SET role=:r, account_status=:s, password_hash=:h, email_verified_at=IFNULL(email_verified_at,NOW()) WHERE id=:id')
        ->execute([':r' => $role, ':s' => 'active', ':h' => $hash, ':id' => $existing['id']]);
    echo "Updated user #{$existing['id']} to $role\n";
} else {
    $pdo->prepare("INSERT INTO users (email, email_normalized, password_hash, role, account_status, is_active, is_verified, email_verified_at) VALUES (:e,:en,:h,:r,'active',1,1,NOW())")
        ->execute([':e' => $email, ':en' => $email, ':h' => $hash, ':r' => $role]);
    $id = (int) $pdo->lastInsertId();
    echo "Created $role #$id ($email)\n";
}
