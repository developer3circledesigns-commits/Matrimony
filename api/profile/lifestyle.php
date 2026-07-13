<?php
declare(strict_types=1);

use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Http\Request;
use Matrimony\Services\ProfileService;

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!Request::is('PUT')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

Csrf::require();
$userId = Auth::require();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
unset($input['csrf']);

try {
    $service = new ProfileService();
    $result = $service->updateLifestyle($userId, $input);

    $horoscopeFields = ['rashi', 'nakshatra', 'time_of_birth', 'place_of_birth'];
    $horoscopeData = array_intersect_key($input, array_flip($horoscopeFields));
    $hResult = true;
    if (!empty($horoscopeData)) {
        $hResult = $service->updateHoroscope($userId, $horoscopeData);
    }

    if ($result === true && $hResult === true) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $err = 'Update failed. Check server error logs for details.';
        if (is_string($result)) $err = $result;
        elseif (is_string($hResult)) $err = $hResult;
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $err], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
