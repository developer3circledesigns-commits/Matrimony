<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Security\RateLimiter;
use Matrimony\Services\EmailOutboxService;

header('Content-Type: application/json; charset=utf-8');
Csrf::require();
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
if (!RateLimiter::attempt('contact:' . $ip, 10, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many messages. Please try again later.']);
    exit;
}

if (!Request::is('POST')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$name    = substr(trim(preg_replace('/[\r\n\0]+/', ' ', (string) ($input['name'] ?? '')) ?? ''), 0, 120);
$email   = trim((string) ($input['email'] ?? ''));
$phone   = substr(trim(preg_replace('/[\r\n\0]+/', ' ', (string) ($input['phone'] ?? '')) ?? ''), 0, 30);
$subject = substr(trim(preg_replace('/[\r\n\0]+/', ' ', (string) ($input['subject'] ?? '')) ?? ''), 0, 160);
$message = trim((string) ($input['message'] ?? ''));

$errors = [];
if ($name === '') $errors[] = 'Name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($subject === '') $errors[] = 'Subject is required';
if ($message === '') $errors[] = 'Message is required';
if (strlen($message) > 5000) $errors[] = 'Message is too long';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode('. ', $errors) . '.']);
    exit;
}

// Contact content is PII: deliver it by email only, never write it to disk.
$body = "Contact form message\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Subject: {$subject}\n\n"
    . $message;
$html = '<p><b>Name:</b> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
    . '<br><b>Email:</b> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8')
    . '<br><b>Phone:</b> ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8')
    . '<br><b>Subject:</b> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
    . '</p><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';

EmailOutboxService::queueToAdmins('contact_inquiry', [
    'message' => $body,
    'subject_line' => '[Contact] ' . $subject,
    'body_html' => $html,
]);

echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
