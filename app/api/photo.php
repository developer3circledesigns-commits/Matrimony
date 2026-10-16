<?php
declare(strict_types=1);

/**
 * Serves profile photos directly from the database.
 *
 * Usage:  /api/photo?id={photo_id}
 *
 * Reads the image binary from the profile_photos.image_data column
 * and outputs it with the correct MIME type, allowing browsers to
 * cache it via standard HTTP headers.
 */

use Matrimony\Database\Connection;

require_once __DIR__ . '/../includes/bootstrap.php';

$photoId = (int) ($_GET['id'] ?? 0);
if ($photoId < 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing or invalid photo id']);
    exit;
}

$pdo = Connection::pdo();
$stmt = $pdo->prepare("SELECT image_data, image_mime, privacy_level, status, user_id FROM profile_photos WHERE id = :id AND image_data IS NOT NULL");
$stmt->execute([':id' => $photoId]);
$row = $stmt->fetch();

if (!$row || $row['image_data'] === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$level = $row['privacy_level'] ?? 'public';
$owner = (int) ($row['user_id'] ?? 0);
$viewer = (int) ($_SESSION['user_id'] ?? 0);
$status = (string) ($row['status'] ?? 'approved');
// Pending/rejected photos are only visible to the owner (or later admin tooling).
if ($status !== 'approved' && $viewer !== $owner) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

// Privacy enforcement: protected/private never served to anonymous or unauthorized viewers.
if ($level !== 'public') {
    if (!$viewer) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    $isOwner = $viewer === $owner;
    $isAdmin = false;
    try {
        $s = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $s->execute([':id' => $viewer]);
        $isAdmin = in_array($s->fetchColumn(), ['admin', 'super_admin'], true);
    } catch (\Throwable $e) {
    }
    if (!$isOwner && !$isAdmin && $level === 'private') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    if (!$isOwner && !$isAdmin && $level !== 'public' && $owner) {
        try {
            $blk = $pdo->prepare('SELECT 1 FROM profile_blocks WHERE blocker_id = :b AND blocked_id = :v LIMIT 1');
            $blk->execute([':b' => $owner, ':v' => $viewer]);
            if ($blk->fetchColumn()) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Photo not found']);
                exit;
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }
    // 'protected' requires login (above) + target profile must be visible
    try {
        $s = $pdo->prepare('SELECT moderation_status, is_hidden FROM profiles WHERE user_id = :u LIMIT 1');
        $s->execute([':u' => $owner]);
        $prof = $s->fetch();
        if ($prof && (($prof['moderation_status'] ?? 'approved') !== 'approved' || (int) ($prof['is_hidden'] ?? 0) === 1)) {
            if (!$isOwner && !$isAdmin) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Photo not found']);
                exit;
            }
        }
    } catch (\Throwable $e) {
    }
}

$mime = $row['image_mime'] ?: 'image/jpeg';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    $mime = 'image/jpeg';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($row['image_data']));
if ($level === 'public') {
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('Pragma: cache');
} else {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}

echo $row['image_data'];
