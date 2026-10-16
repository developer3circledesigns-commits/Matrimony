<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Authorization;
use Matrimony\Services\PaymentService;

final class PaymentController extends Controller
{
    public function __invoke(Request $request): void
    {
        $userId = Authorization::requireLogin('/users/login');
        $action = Request::segment(1) ?? 'history';
        if ($action === 'return') {
            $ref = substr(trim($_GET['ref'] ?? ''), 0, 40);
            $this->view('payment/views/return', ['pageTitle' => 'Payment result', 'ref' => $ref], 'main');
            return;
        }
        $purchases = PaymentService::forUser($userId, 50);
        $this->view('payment/views/history', ['pageTitle' => 'Payment history', 'purchases' => $purchases], 'main');
    }
}

(new PaymentController())(new Request());
