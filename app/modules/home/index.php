<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Database\Connection;

final class HomeController extends Controller
{
    public function __invoke(Request $request): void
    {
        $pdo = Connection::pdo();

        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, u.is_verified,
                   p.first_name, p.last_name, p.gender, p.date_of_birth, p.city, p.state,
                   (SELECT path FROM profile_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) AS primary_photo
            FROM users u
            JOIN profiles p ON p.user_id = u.id
            WHERE u.is_active = 1
            ORDER BY u.created_at DESC
            LIMIT 6
        ");
        $stmt->execute();
        $recentMembers = $stmt->fetchAll();

        foreach ($recentMembers as &$member) {
            $age = 0;
            if (!empty($member['date_of_birth']) && $member['date_of_birth'] !== '0000-00-00') {
                try {
                    $birth = new \DateTime($member['date_of_birth']);
                    $now = new \DateTime();
                    $age = (int) $now->diff($birth)->y;
                } catch (\Throwable $e) {
                    $age = 0;
                }
            }
            $member['age'] = $age;
            $member['initials'] = strtoupper(substr($member['first_name'] ?? '?', 0, 1));
        }
        unset($member);

        $this->view('home/views/index', [
            'pageTitle' => 'Home',
            'isLoggedIn' => Auth::id() !== null,
            'recentMembers' => $recentMembers,
        ], 'main');
    }
}

(new HomeController())(new Request());
