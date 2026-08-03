<?php
declare(strict_types=1);

/**
 * Migration: Move existing photo files from the filesystem into the database.
 *
 * Reads every row in profile_photos that still has a filesystem path
 * (path starting with /uploads/), reads the file from disk, stores the
 * binary content in image_data + image_mime, and updates the path to
 * point to the new serve endpoint.
 *
 * Run from CLI:  php app/database/migrations/007_migrate_photos_to_db.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

use Matrimony\Database\Connection;

$pdo = Connection::pdo();

echo "Fetching photos with filesystem paths...\n";

$stmt = $pdo->prepare("SELECT id, path, image_data FROM profile_photos WHERE path LIKE '/uploads/%' AND image_data IS NULL");
$stmt->execute();
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "No photos to migrate.\n";
    exit(0);
}

echo "Found " . count($rows) . " photo(s) to migrate.\n";

$count = 0;
foreach ($rows as $row) {
    $filePath = BASE_PATH . $row['path'];

    if (!is_file($filePath)) {
        echo "  SKIP photo #{$row['id']}: file not found at {$filePath}\n";
        continue;
    }

    $imageData = file_get_contents($filePath);
    if ($imageData === false) {
        echo "  SKIP photo #{$row['id']}: could not read {$filePath}\n";
        continue;
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($imageData);

    $servePath = '/api/photo?id=' . $row['id'];

    $upd = $pdo->prepare("UPDATE profile_photos SET path = :path, image_data = :data, image_mime = :mime WHERE id = :id");
    $upd->execute([
        ':path' => $servePath,
        ':data' => $imageData,
        ':mime' => $mime,
        ':id'   => $row['id'],
    ]);

    $count++;
    echo "  Migrated photo #{$row['id']}: {$row['path']} → {$servePath}\n";
}

echo "Done. {$count} photo(s) migrated.\n";
