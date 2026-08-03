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
$stmt = $pdo->prepare("SELECT image_data, image_mime FROM profile_photos WHERE id = :id AND image_data IS NOT NULL");
$stmt->execute([':id' => $photoId]);
$row = $stmt->fetch();

if (!$row || $row['image_data'] === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$mime = $row['image_mime'] ?: 'image/jpeg';

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($row['image_data']));
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header('Pragma: cache');

echo $row['image_data'];
