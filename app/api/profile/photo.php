<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

$userId = Auth::require();
$service = new ProfileService();
$params = $_SERVER['API_PARAMS'] ?? [];

// GET — list photos (already included in full profile)
if (Request::is('GET')) {
    $pdo = \Matrimony\Database\Connection::pdo();
    $stmt = $pdo->prepare("SELECT * FROM profile_photos WHERE user_id = :uid ORDER BY is_primary DESC, id ASC");
    $stmt->execute([':uid' => $userId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// POST — upload photo
if (Request::is('POST')) {
    Csrf::require();
    if (empty($_FILES['photo'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No photo uploaded', 'debug' => [
            'files_keys' => array_keys($_FILES),
            'post_keys' => array_keys($_POST),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'none',
        ]]);
        exit;
    }
    $result = $service->addPhoto($userId, $_FILES['photo']);
    if (!$result) {
        http_response_code(400);
        $f = $_FILES['photo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $reason = 'unknown';
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds post_max_size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            ];
            $reason = $errors[$f['error']] ?? 'Upload error code: ' . $f['error'];
        } elseif (!in_array($ext, $allowed)) {
            $reason = 'File extension "' . $ext . '" not allowed';
        } else {
            $dir = BASE_PATH . '/uploads/' . $userId;
            if (!is_dir($dir)) {
                $reason = 'Could not create directory: ' . $dir;
            } elseif (!is_writable($dir)) {
                $reason = 'Directory not writable: ' . $dir;
            } else {
                $reason = 'move_uploaded_file failed (tmp: ' . $f['tmp_name'] . ', size: ' . $f['size'] . ' bytes)';
            }
        }
        echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $reason, 'debug' => [
            'file_size_bytes' => $f['size'],
            'file_size_mb' => round($f['size'] / 1048576, 2),
            'error_code' => $f['error'],
            'file_tmp' => $f['tmp_name'],
            'file_exists_tmp' => $f['tmp_name'] ? file_exists($f['tmp_name']) : false,
            'ini_upload_max' => ini_get('upload_max_filesize'),
            'ini_post_max' => ini_get('post_max_size'),
        ]]);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// DELETE /api/profile/photo/{id} — delete photo
if (Request::is('DELETE')) {
    Csrf::require();
    $photoId = (int) ($params[0] ?? 0);
    if (!$photoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Photo ID required']);
        exit;
    }
    $result = $service->deletePhoto($userId, $photoId);
    http_response_code($result ? 200 : 400);
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// PUT — set primary (via id in params or _method=PUT)
if (Request::is('PUT') || ($_POST['_method'] ?? '') === 'PUT') {
    Csrf::require();
    $photoId = (int) ($params[0] ?? $_POST['photo_id'] ?? 0);
    if (!$photoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Photo ID required']);
        exit;
    }
    $result = $service->setPrimaryPhoto($userId, $photoId);
    http_response_code($result ? 200 : 400);
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
