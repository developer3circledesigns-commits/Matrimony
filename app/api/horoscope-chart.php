<?php
declare(strict_types=1);

/**
 * Serves a member's horoscope chart ("kattam") directly from the database.
 *
 * Usage:  /api/horoscope-chart?user={user_id}
 *
 * Visible to the profile owner and to admins. Reads the binary from
 * profile_horoscope.kattam_data and streams it with the stored MIME type.
 */

use Matrimony\Database\Connection;

require_once __DIR__ . '/../includes/bootstrap.php';

$userId = (int) ($_GET['user'] ?? $_GET['id'] ?? 0);
if ($userId < 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing or invalid user id']);
    exit;
}

$viewer = (int) ($_SESSION['user_id'] ?? 0);
$isOwner = $viewer === $userId;
$isAdmin = false;
if ($viewer && !$isOwner) {
    try {
        $s = Connection::pdo()->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $s->execute([':id' => $viewer]);
        $isAdmin = in_array($s->fetchColumn(), ['admin', 'super_admin'], true);
    } catch (\Throwable $e) {
    }
}
if (!$isOwner && !$isAdmin) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$pdo = Connection::pdo();
$stmt = $pdo->prepare('SELECT kattam_data, kattam_mime FROM profile_horoscope WHERE user_id = :u AND kattam_data IS NOT NULL LIMIT 1');
$stmt->execute([':u' => $userId]);
$row = $stmt->fetch();

if (!$row || $row['kattam_data'] === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Chart not found']);
    exit;
}

$mime = (string) ($row['kattam_mime'] ?? '');
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    $mime = 'image/jpeg';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen((string) $row['kattam_data']));
header('Content-Disposition: inline');
header('Cache-Control: private, max-age=300');
echo $row['kattam_data'];
