<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Services\PlanService;
use Matrimony\Services\MembershipService;

final class PlansController extends Controller
{
    public function __invoke(Request $request): void
    {
        $plans = PlanService::activePlans();
        $userId = Auth::id();
        $membership = $userId ? MembershipService::activeFor($userId) : null;
        $pending = null;
        if ($userId) {
            try {
                $pdo = \Matrimony\Database\Connection::pdo();
                $s = $pdo->prepare("SELECT p.public_reference, p.plan_name_snapshot, p.payment_status, ar.status AS activation FROM purchases p LEFT JOIN activation_requests ar ON ar.purchase_id = p.id WHERE p.user_id = :u AND (p.payment_status IN ('pending','created') OR ar.status IN ('awaiting_admin','on_hold')) ORDER BY p.id DESC LIMIT 5");
                $s->execute([':u' => $userId]);
                $pending = $s->fetchAll();
            } catch (\Throwable $e) {
                $pending = [];
            }
        }
        $isAdmin = false;
        try {
            $me = \Matrimony\Http\Authorization::currentUser();
            $isAdmin = $me && in_array($me['role'] ?? '', ['admin', 'super_admin'], true);
        } catch (\Throwable $e) {
        }
        $this->view('plans/views/index', [
            'pageTitle' => 'Membership Plans',
            'plans' => $plans,
            'membership' => $membership,
            'pending' => $pending,
            'isLoggedIn' => $userId !== null,
            'csrfToken' => Csrf::token(),
            'migrationPending' => !\Matrimony\Database\Schema::paymentsReady(),
            'isAdmin' => $isAdmin,
            'gatewayConfigured' => \Matrimony\Services\PaymentService::gatewayConfigured(),
        ], 'main');
    }
}

(new PlansController())(new Request());
