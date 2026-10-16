<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Authorization;
use Matrimony\Http\Csrf;
use Matrimony\Services\PlanService;
use Matrimony\Database\Connection;

final class CheckoutController extends Controller
{
    public function __invoke(Request $request): void
    {
        $userId = Authorization::requireLogin('/users/login');
        $slug = Request::segment(1) ?? '';
        $plan = PlanService::findActive($slug);
        if (!$plan) {
            http_response_code(404);
            $this->view('plans/views/index', ['pageTitle' => 'Plans', 'plans' => PlanService::activePlans(), 'membership' => null, 'pending' => [], 'isLoggedIn' => true, 'csrfToken' => Csrf::token()], 'main');
            return;
        }
        // Find (or require) a recent pending purchase for this plan
        $pdo = Connection::pdo();
        $ref = $_GET['ref'] ?? '';
        $orderId = $_GET['order'] ?? '';
        $purchase = null;
        if ($ref !== '' || $orderId !== '') {
            try {
                $s = $pdo->prepare('SELECT * FROM purchases WHERE user_id = :u AND (public_reference = :r OR gateway_order_id = :o) LIMIT 1');
                $s->execute([':u' => $userId, ':r' => $ref, ':o' => $orderId]);
                $purchase = $s->fetch();
            } catch (\Throwable $e) {
                $purchase = false; // payments tables missing pre-migration
            }
        }
        $cfg = require BASE_PATH . '/app/config/payments.php';
        $this->view('checkout/views/index', [
            'pageTitle' => 'Checkout · ' . $plan['name'],
            'plan' => $plan,
            'purchase' => $purchase,
            'keyId' => $cfg['razorpay']['key_id'],
            'csrfToken' => Csrf::token(),
            'userEmail' => $this->emailOf($userId),
            'gatewayConfigured' => \Matrimony\Services\PaymentService::gatewayConfigured(),
        ], 'main');
    }

    private function emailOf(int $id): string
    {
        try {
            $pdo = Connection::pdo();
            $s = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
            $s->execute([':id' => $id]);
            return (string) $s->fetchColumn();
        } catch (\Throwable $e) {
            return '';
        }
    }
}

(new CheckoutController())(new Request());
