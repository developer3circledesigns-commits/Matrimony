<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Authorization;
use Matrimony\Services\MembershipService;
use Matrimony\Services\PaymentService;
use Matrimony\Services\NotificationService;

final class MembershipController extends Controller
{
    public function __invoke(Request $request): void
    {
        $userId = Authorization::requireLogin('/users/login');
        $membership = MembershipService::activeFor($userId);
        $purchases = PaymentService::forUser($userId, 20);
        $notes = NotificationService::forUser($userId, 10);
        $this->view('membership/views/index', [
            'pageTitle' => 'My membership',
            'membership' => $membership,
            'purchases' => $purchases,
            'notes' => $notes,
        ], 'main');
    }
}

(new MembershipController())(new Request());
