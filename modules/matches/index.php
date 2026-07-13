<?php
declare(strict_types=1);

use Matrimony\Http\Controller;
use Matrimony\Http\Request;
use Matrimony\Http\Auth;
use Matrimony\Http\Csrf;
use Matrimony\Services\CompatibilityService;

require_once __DIR__ . '/controllers/ActionController.php';

final class MatchesController extends Controller
{
    public function __invoke(Request $request): void
    {
        $action = Request::segment(1);

        if ($action !== null) {
            $this->handleSubPage($action);
            return;
        }

        $userId = Auth::id();
        $suggestions = [];
        $csrfToken = '';

        if ($userId) {
            try {
                $compat = new CompatibilityService();
                $suggestions = $compat->getTopMatches($userId, 6);
            } catch (\Throwable $e) {
                $suggestions = [];
            }
        }
        try {
            $csrfToken = Csrf::token();
        } catch (\Throwable $e) {
            $csrfToken = '';
        }

        $this->view('matches/views/index', [
            'pageTitle' => 'Profile Matches',
            'isLoggedIn' => $userId !== null,
            'userId' => $userId,
            'suggestions' => $suggestions,
            'csrfToken' => $csrfToken,
        ], 'main');
    }

    private function handleSubPage(string $action): void
    {
        $userId = Auth::id();
        if (!$userId) {
            $this->view('matches/views/index', [
                'pageTitle' => 'Profile Matches',
                'isLoggedIn' => false,
                'userId' => null,
                'suggestions' => [],
                'csrfToken' => '',
            ], 'main');
            return;
        }

        $csrfToken = Csrf::token();
        $actionCtrl = new ActionController();
        $profiles = [];
        $pageTitle = '';
        $view = '';

        switch ($action) {
            case 'who-viewed-me':
                $pageTitle = 'Who Viewed Me';
                $view = 'matches/views/who-viewed-me';
                $profiles = $actionCtrl->getWhoViewedMe($userId, 999);
                break;
            case 'interests':
                $pageTitle = 'Interests Received';
                $view = 'matches/views/interests';
                $profiles = $actionCtrl->getInterestsReceived($userId, 999);
                break;
            case 'mutual':
                $pageTitle = 'Mutual Matches';
                $view = 'matches/views/mutual';
                $profiles = $actionCtrl->getMutualMatches($userId, 999);
                break;
            case 'shortlists':
                $pageTitle = 'My Shortlists';
                $view = 'matches/views/shortlists';
                $profiles = $actionCtrl->getMyShortlists($userId, 999);
                break;
            default:
                http_response_code(404);
                $this->view('matches/views/index', [
                    'pageTitle' => 'Profile Matches',
                    'isLoggedIn' => true,
                    'userId' => $userId,
                    'suggestions' => [],
                    'csrfToken' => $csrfToken,
                ], 'main');
                return;
        }

        $this->view($view, [
            'pageTitle' => $pageTitle,
            'profiles' => $profiles,
            'csrfToken' => $csrfToken,
        ], 'main');
    }
}

(new MatchesController())(new Request());
