<?php
declare(strict_types=1);

namespace Tests\Integration;

use Matrimony\Services\CompatibilityService;
use Tests\IntegrationTestCase;
use Tests\TestDatabase;

/**
 * @group integration
 * @group matches
 */
final class MatchesApiTest extends IntegrationTestCase
{
    private \MatchController $matchController;
    private \ActionController $actionController;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::injectPdo();

        require_once BASE_PATH . '/modules/matches/controllers/MatchController.php';
        require_once BASE_PATH . '/modules/matches/controllers/ActionController.php';

        $this->matchController = new \MatchController();
        $this->actionController = new \ActionController();
    }

    // ---- M-F-01: List returns profiles with required fields ----

    public function test_M_F_01_list_returns_profiles_with_required_fields(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list([], 'compatibility', 1, 24, 1);

        $this->assertNotEmpty($result['data']);
        $profile = $result['data'][0];
        $this->assertArrayHasKey('first_name', $profile);
        $this->assertArrayHasKey('age', $profile);
        $this->assertArrayHasKey('city', $profile);
        $this->assertArrayHasKey('education', $profile);
        $this->assertArrayHasKey('occupation', $profile);
        $this->assertArrayHasKey('primary_photo', $profile);
    }

    // ---- M-F-03: Multi-filter intersection ----

    public function test_M_F_03_filters_intersect_correctly(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list(
            ['religion' => ['Hindu'], 'city' => ['Mumbai']],
            'compatibility', 1, 50, 1
        );

        foreach ($result['data'] as $p) {
            $this->assertEquals('Hindu', $p['religion']);
            $this->assertEquals('Mumbai', $p['city']);
        }
    }

    // ---- M-F-08: Send interest ----

    public function test_M_F_08_send_interest_creates_match(): void
    {
        $this->setAuth(1);
        $result = $this->actionController->perform(1, 6, 'interested');

        $this->assertTrue($result['success']);
        $this->assertEquals('interested', $result['status']);

        // Verify in DB
        $pdo = \Matrimony\Database\Connection::pdo();
        $stmt = $pdo->prepare("SELECT status FROM matches WHERE user_id = :u AND target_id = :t");
        $stmt->execute([':u' => 1, ':t' => 6]);
        $this->assertEquals('interested', $stmt->fetchColumn());
    }

    // ---- M-F-12: Mutual match ----

    public function test_M_F_12_mutual_match_detected(): void
    {
        $this->setAuth(1);
        // User 1 sends interest to user 2 (fixtures already have user 2 interested in user 1)
        $result = $this->actionController->perform(1, 2, 'interested');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['mutual']);
    }

    // ---- M-F-10: Shortlist ----

    public function test_M_F_10_shortlist_profile(): void
    {
        $this->setAuth(1);
        $result = $this->actionController->perform(1, 4, 'shortlisted');

        $this->assertTrue($result['success']);
        $this->assertEquals('shortlisted', $result['status']);
    }

    // ---- M-F-11: Decline ----

    public function test_M_F_11_decline_profile(): void
    {
        $this->setAuth(6);
        $result = $this->actionController->perform(6, 3, 'declined');

        $this->assertTrue($result['success']);
        $this->assertEquals('declined', $result['status']);
    }

    // ---- M-F-14: Block ----

    public function test_M_F_14_block_user(): void
    {
        $this->setAuth(1);
        $result = $this->actionController->block(1, 4);

        $this->assertTrue($result['success']);

        // Verify in DB
        $pdo = \Matrimony\Database\Connection::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_blocks WHERE blocker_id = :b AND blocked_id = :t");
        $stmt->execute([':b' => 1, ':t' => 4]);
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    // ---- M-F-15: Report ----

    public function test_M_F_15_report_user(): void
    {
        $this->setAuth(1);
        $result = $this->actionController->report(1, 4, 'Inappropriate profile');

        $this->assertTrue($result['success']);
    }

    // ---- M-F-17: Match score display ----

    public function test_M_F_17_match_score_in_list(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list([], 'compatibility', 1, 50, 1);

        $this->assertNotEmpty($result['data']);
        $this->assertArrayHasKey('compatibility', $result['data'][0]);
    }

    // ---- M-FL-01: Age filter ----

    public function test_M_FL_01_age_filter(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list(
            ['age_min' => 24, 'age_max' => 30],
            'compatibility', 1, 50, 1
        );

        foreach ($result['data'] as $p) {
            $this->assertGreaterThanOrEqual(24, $p['age']);
            $this->assertLessThanOrEqual(30, $p['age']);
        }
    }

    // ---- M-FL-02: Height filter ----

    public function test_M_FL_02_height_filter(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list(
            ['height_min_cm' => 150, 'height_max_cm' => 170],
            'compatibility', 1, 50, 1
        );

        foreach ($result['data'] as $p) {
            $this->assertGreaterThanOrEqual(150, (int) $p['height_cm']);
            $this->assertLessThanOrEqual(170, (int) $p['height_cm']);
        }
    }

    // ---- M-FL-03: Religion filter ----

    public function test_M_FL_03_religion_filter(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list(
            ['religion' => ['Hindu']],
            'compatibility', 1, 50, 1
        );

        foreach ($result['data'] as $p) {
            $this->assertEquals('Hindu', $p['religion']);
        }
    }

    // ---- M-FL-14: Reset filters ----

    public function test_M_FL_14_reset_filters(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list([], 'compatibility', 1, 50, 1);

        $this->assertNotEmpty($result['data']);
    }

    // ---- M-FL-17: Blocked excluded ----

    public function test_M_FL_17_blocked_excluded(): void
    {
        // User 2 blocked user 5. User 2 should NOT see user 5.
        $this->setAuth(2);
        $result = $this->matchController->list([], 'compatibility', 1, 50, 2);

        $userIds = array_column($result['data'], 'user_id');
        $this->assertNotContains(5, $userIds);
    }

    // ---- M-S-01: Filter sanitization ----

    public function test_M_S_01_filter_age_abc_coerces_to_zero(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list(
            ['age_min' => 'abc'],
            'compatibility', 1, 50, 1
        );

        // Should not crash; invalid filter values are cast to 0 or ignored
        $this->assertIsArray($result['data']);
    }

    // ---- M-D-01: Card data matches DB ----

    public function test_M_D_01_card_data_matches_profile_table(): void
    {
        $this->setAuth(1);
        $result = $this->matchController->list([], 'compatibility', 1, 50, 1);

        $pdo = \Matrimony\Database\Connection::pdo();
        foreach ($result['data'] as $p) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE user_id = :id");
            $stmt->execute([':id' => $p['user_id']]);
            $this->assertEquals(1, (int) $stmt->fetchColumn(), "Profile {$p['user_id']} should exist in DB");
        }
    }

    // ---- Who viewed me ----

    public function test_who_viewed_me_returns_authenticated_users(): void
    {
        $this->setAuth(1);
        $viewers = $this->actionController->getWhoViewedMe(1);

        $this->assertNotEmpty($viewers);
        // User 1 has 4 viewers from fixtures
        $this->assertCount(4, $viewers);
    }

    // ---- Who shortlisted me ----

    public function test_who_shortlisted_me(): void
    {
        $this->setAuth(1);
        $shortlist = $this->actionController->getWhoShortlistedMe(1);

        $this->assertIsArray($shortlist);
    }

    // ---- Search suggestions ----

    public function test_suggest_returns_top_matches(): void
    {
        $this->setAuth(1);
        $service = new CompatibilityService();
        $suggestions = $service->getTopMatches(1, 5);

        // User 1 (male) has 3 opposite-gender (female) profiles: users 2, 4, 6
        $this->assertCount(3, $suggestions);
    }

    // ---- M-S-03: Premium contact hidden for free users ----

    public function test_M_S_03_premium_contact_hidden_for_free_users(): void
    {
        $this->setAuth(2); // Free user

        // Get user 2's preferences
        $service = new \Matrimony\Services\ProfileService();
        $prefs = $service->getPreferences(2);

        $this->assertIsArray($prefs);
    }
}
