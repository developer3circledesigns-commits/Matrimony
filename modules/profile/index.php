<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Services\ProfileService;

require_once BASE_PATH . '/modules/matches/controllers/ActionController.php';

final class ProfileController extends Controller
{
    public function __invoke(Request $request): void
    {
        $targetId = Request::segment(1);
        if ($targetId !== null && is_numeric($targetId)) {
            $this->showPublicProfile((int) $targetId);
            return;
        }

        $userId = Auth::id();
        $profile = null;
        $csrfToken = '';
        $errorMsg = null;
        $sidebarData = ['views' => [], 'interests' => [], 'matches' => [], 'shortlists' => []];

        if ($userId) {
            try {
                $service = new ProfileService();
                $profile = $service->getFullProfile($userId);
                if (!$profile) {
                    $pdo = \Matrimony\Database\Connection::pdo();
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
                    $stmt->execute([':id' => $userId]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $name = explode('@', $user['email'])[0];
                        $pdo->prepare("INSERT IGNORE INTO profiles (user_id, first_name, last_name, gender, date_of_birth) VALUES (:uid, :fn, :ln, 'other', '2000-01-01')")
                            ->execute([':uid' => $userId, ':fn' => $name, ':ln' => $name]);
                        $profile = $service->getFullProfile($userId);
                    }
                }
                $csrfToken = Csrf::token();

                // Fetch sidebar data
                if ($profile) {
                    $actionCtrl = new ActionController();
                    $sidebarData['views'] = $actionCtrl->getWhoViewedMe($userId, 999);
                    $sidebarData['interests'] = $actionCtrl->getInterestsReceived($userId, 999);
                    $sidebarData['matches'] = $actionCtrl->getMutualMatches($userId, 999);
                    $sidebarData['shortlists'] = $actionCtrl->getMyShortlists($userId, 999);
                }
        } catch (\Throwable $e) {
                error_log('Profile load error: ' . $e->getMessage());
                $profile = null;
                $errorMsg = $e->getMessage();
            }
        }

        $this->view('profile/views/index', [
            'pageTitle' => 'My Profile',
            'isLoggedIn' => $userId !== null,
            'profile' => $profile,
            'csrfToken' => $csrfToken,
            'errorMsg' => $errorMsg ?? null,
            'sidebarData' => $sidebarData,
        ], 'main');
    }

    private function showPublicProfile(int $targetId): void
    {
        $service = new ProfileService();
        $profile = $service->getViewerProfile($targetId);
        $currentUserId = Auth::id();
        $csrfToken = Csrf::token();
        $existingStatus = null;
        
        if ($profile) {
            $profile['age'] = $service->calcAge($profile['date_of_birth'] ?? '');
        }
        
        if ($currentUserId && $profile && $currentUserId !== (int) $profile['user_id']) {
            $pdo = \Matrimony\Database\Connection::pdo();
            $stmt = $pdo->prepare("SELECT status FROM matches WHERE user_id = :uid AND target_id = :tid");
            $stmt->execute([':uid' => $currentUserId, ':tid' => (int) $profile['user_id']]);
            $existingStatus = $stmt->fetchColumn();
        }

        $this->view('profile/views/public', [
            'pageTitle' => ($profile ? e($profile['first_name'] ?? '') . ' ' . e($profile['last_name'] ?? '') : 'Profile') . ' | Matrimony',
            'profile' => $profile,
            'isLoggedIn' => $currentUserId !== null,
            'currentUserId' => $currentUserId,
            'csrfToken' => $csrfToken,
            'existingStatus' => $existingStatus,
        ], 'main');
    }
}

(new ProfileController())(new Request());
