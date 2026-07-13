<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

$service = new ProfileService();

if (Request::is('GET')) {
    $params = $_SERVER['API_PARAMS'] ?? [];

    if (!empty($params)) {
        $targetId = (int) $params[0];

        // Browser visits to /api/profile/{id} should show the HTML page
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'text/html') !== false && strpos($accept, 'application/json') === false) {
            header('Location: /profile/' . $targetId, true, 302);
            exit;
        }

        $profile = $service->getViewerProfile($targetId);
        if (!$profile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Profile not found']);
            exit;
        }
        $profile['age'] = $service->calcAge($profile['date_of_birth'] ?? '');
        echo json_encode(['success' => true, 'data' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $userId = Auth::require();
    $profile = $service->getFullProfile($userId);
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        exit;
    }
    $profile['age'] = $service->calcAge($profile['date_of_birth'] ?? '');
    echo json_encode(['success' => true, 'data' => $profile], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (Request::is('PUT')) {
    Csrf::require();
    $userId = Auth::require();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $params = $_SERVER['API_PARAMS'] ?? [];
    $section = $params[0] ?? $input['section'] ?? 'personal';
    unset($input['section'], $input['csrf']);

    switch ($section) {
        case 'personal':
            $result = $service->updatePersonal($userId, $input);
            break;
        case 'family':
            $result = $service->updateFamily($userId, $input);
            break;
        case 'lifestyle':
            $ok = $service->updateLifestyle($userId, $input);
            $horoscopeFields = ['rashi', 'nakshatra', 'time_of_birth', 'place_of_birth'];
            $horoscopeData = array_intersect_key($input, array_flip($horoscopeFields));
            if (!empty($horoscopeData)) {
                $hOk = $service->updateHoroscope($userId, $horoscopeData);
                $ok = $ok && $hOk;
            }
            $result = $ok;
            break;
        case 'horoscope':
            $result = $service->updateHoroscope($userId, $input);
            break;
        case 'preferences':
            $result = $service->updatePreferences($userId, $input);
            break;
        case 'privacy':
            $result = $service->updatePrivacy($userId, $input);
            break;
        default:
            $result = false;
            break;
    }

    http_response_code($result ? 200 : 400);
    echo json_encode(['success' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
