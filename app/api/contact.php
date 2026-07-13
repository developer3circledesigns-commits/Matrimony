<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Http\Request;

if (!Request::is('POST')) {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name    = trim($input['name'] ?? '');
$email   = trim($input['email'] ?? '');
$phone   = trim($input['phone'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

$errors = [];
if ($name === '') $errors[] = 'Name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($subject === '') $errors[] = 'Subject is required';
if ($message === '') $errors[] = 'Message is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode('. ', $errors) . '.']);
    exit;
}

// Store contact message in file-based log
$logDir = BASE_PATH . '/app/storage/contacts';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$entry = json_encode([
    'name'    => $name,
    'email'   => $email,
    'phone'   => $phone,
    'subject' => $subject,
    'message' => $message,
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
    'created_at' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);

$logFile = $logDir . '/' . date('Y-m-d') . '.log';
file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
