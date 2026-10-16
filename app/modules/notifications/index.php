<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Authorization;
use Matrimony\Services\NotificationService;

final class NotificationsController extends Controller
{
    public function __invoke(Request $request): void
    {
        $userId = Authorization::requireLogin('/users/login');
        if ($request::is('POST') && isset($_POST['mark_read'])) {
            \Matrimony\Http\Csrf::require();
            NotificationService::markRead($userId);
            $this->redirect('/notifications');
            return;
        }
        $items = NotificationService::forUser($userId, 50);
        $this->view('notifications/views/index', ['pageTitle' => 'Notifications', 'items' => $items, 'csrfToken' => \Matrimony\Http\Csrf::token()], 'main');
    }
}

(new NotificationsController())(new Request());
