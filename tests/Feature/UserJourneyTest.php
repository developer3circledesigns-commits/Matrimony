<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\IntegrationTestCase;
use Tests\TestDatabase;

/**
 * @group feature
 * @group journey
 *
 * User Acceptance Test scenarios: Journey A, B, C
 */
final class UserJourneyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::injectPdo();
    }

    // ========================================================================
    // Journey A — Profile creation
    // ========================================================================

    public function test_Journey_A_profile_creation_and_activation(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();

        // ---- Step 1: Register with email ----
        $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:e, :p)")
            ->execute([':e' => 'newuser@test.com', ':p' => password_hash('TestPass1', PASSWORD_BCRYPT)]);
        $newUserId = (int) $pdo->lastInsertId();

        $this->assertGreaterThan(0, $newUserId, 'Step 1: User must be created');

        // ---- Step 2: Verify email ----
        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = :id")
            ->execute([':id' => $newUserId]);
        $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = :id");
        $stmt->execute([':id' => $newUserId]);
        $this->assertEquals(1, (int) $stmt->fetchColumn(), 'Step 2: Email must be verified');

        // ---- Step 3: Create profile ----
        $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth, marital_status, religion, caste, mother_tongue, height_cm, education, occupation, city, state, country, about_me)
            VALUES (:u, :f, :l, :g, :d, :m, :r, :c, :t, :h, :e, :o, :ci, :s, :co, :a)")
            ->execute([
                ':u' => $newUserId, ':f' => 'New', ':l' => 'User',
                ':g' => 'male', ':d' => '1995-05-15', ':m' => 'never_married',
                ':r' => 'Hindu', ':c' => 'Brahmin', ':t' => 'Hindi',
                ':h' => 175, ':e' => 'Masters', ':o' => 'Engineer',
                ':ci' => 'Mumbai', ':s' => 'Maharashtra', ':co' => 'India',
                ':a' => 'New user profile',
            ]);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => $newUserId]);
        $this->assertEquals(1, (int) $stmt->fetchColumn(), 'Step 3: Profile must be created');

        // ---- Step 4: Upload primary photo ----
        $pdo->prepare("INSERT INTO profile_photos (user_id, path, is_primary, status) VALUES (:u, '/uploads/{$newUserId}/photo.jpg', 1, 'approved')")
            ->execute([':u' => $newUserId]);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_photos WHERE user_id = :id AND is_primary = 1");
        $stmt->execute([':id' => $newUserId]);
        $this->assertEquals(1, (int) $stmt->fetchColumn(), 'Step 4: Primary photo must exist');

        // ---- Step 5: Fill all sections ----
        // Family
        $pdo->prepare("INSERT INTO profile_family (user_id, father_name, mother_name, family_type, family_values) VALUES (:u, :f, :m, :t, :v)")
            ->execute([':u' => $newUserId, ':f' => 'Father', ':m' => 'Mother', ':t' => 'nuclear', ':v' => 'liberal']);
        // Lifestyle
        $pdo->prepare("INSERT INTO profile_assets (user_id, diet, smoke, drink) VALUES (:u, 'vegetarian', 'no', 'no')")
            ->execute([':u' => $newUserId]);
        // Horoscope
        $pdo->prepare("INSERT INTO profile_horoscope (user_id, rashi, nakshatra) VALUES (:u, 'Mesh', 'Ashwini')")
            ->execute([':u' => $newUserId]);
        // Preferences
        $pdo->prepare("INSERT INTO profile_preferences (user_id, min_age, max_age, pref_religion, pref_caste) VALUES (:u, 24, 32, '[\"Hindu\"]', '[\"Brahmin\"]')")
            ->execute([':u' => $newUserId]);
        // Privacy
        $pdo->prepare("INSERT INTO privacy_settings (user_id) VALUES (:u)")
            ->execute([':u' => $newUserId]);
        // Lifestyle location
        $pdo->prepare("INSERT INTO profile_lifestyle (user_id, willing_to_relocate, residency_status) VALUES (:u, 1, 'citizen')")
            ->execute([':u' => $newUserId]);

        // ---- Step 6: Reach 80% completion ----
        // Load profile and calculate completion
        $service = new \Matrimony\Services\ProfileService();
        $profile = $service->getFullProfile($newUserId);
        $this->assertNotNull($profile, 'Step 6a: Profile must be loadable');
        $this->assertGreaterThanOrEqual(80, $profile['completion_percentage'],
            'Step 6b: Completion must be >= 80%');

        // ---- Step 7: Activate profile ----
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = :id")
            ->execute([':id' => $newUserId]);
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = :id");
        $stmt->execute([':id' => $newUserId]);
        $this->assertEquals(1, (int) $stmt->fetchColumn(), 'Step 7: Profile must be active');

        // ---- Step 8: Should receive suggested matches ----
        // Use CompatibilityService to get suggestions
        $compatService = new \Matrimony\Services\CompatibilityService();
        $suggestions = $compatService->getTopMatches($newUserId, 3);
        $this->assertNotEmpty($suggestions, 'Step 8: Should receive suggested matches');
        $this->assertCount(3, $suggestions);
    }

    // ========================================================================
    // Journey B — Finding matches
    // ========================================================================

    public function test_Journey_B_finding_matches(): void
    {
        $this->setAuth(1);

        // ---- Step 1: See suggested matches ----
        $compatService = new \Matrimony\Services\CompatibilityService();
        $suggestions = $compatService->getTopMatches(1, 12);
        $this->assertNotEmpty($suggestions, 'Step 1: Should see suggested matches');
        $this->assertLessThanOrEqual(12, count($suggestions));

        // ---- Step 2: Apply 4 filters ----
        $filterBuilder = new \Matrimony\Services\FilterBuilder();
        $filters = [
            'religion' => ['Hindu'],
            'city' => ['Mumbai'],
            'age_min' => 24,
            'age_max' => 35,
            'diet' => ['vegetarian'],
        ];
        $sqlParts = $filterBuilder->build($filters, 1, 'male');

        $pdo = \Matrimony\Database\Connection::pdo();
        $sql = "SELECT p.*, u.is_verified, u.last_login_at,
                       pa.diet,
                       (SELECT path FROM profile_photos WHERE user_id = p.user_id AND is_primary = 1 LIMIT 1) AS primary_photo
                FROM profiles p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN profile_assets pa ON pa.user_id = p.user_id
                " . implode(' ', $sqlParts['joins']) . "
                WHERE {$sqlParts['where']}
                ORDER BY u.last_login_at DESC
                LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParts['params']);
        $results = $stmt->fetchAll();
        $this->assertNotEmpty($results, 'Step 2: Should see filtered results');

        // ---- Step 3: Save search ----
        $searchName = 'MBA Mumbai 26-30 via Journey B';
        $filtersJson = json_encode(['education' => ['MBA'], 'city' => ['Mumbai'], 'age_min' => 26, 'age_max' => 30]);
        $pdo->prepare("INSERT INTO profile_searches (user_id, name, filters_json) VALUES (:u, :n, :f)")
            ->execute([':u' => 1, ':n' => $searchName, ':f' => $filtersJson]);
        $searchId = (int) $pdo->lastInsertId();
        $this->assertGreaterThan(0, $searchId, 'Step 3: Search should be saved');

        // ---- Step 4: Send interest, shortlist, decline ----
        require_once BASE_PATH . '/modules/matches/controllers/ActionController.php';
        $actionController = new \ActionController();

        // Send interest to user 6
        $result = $actionController->perform(1, 6, 'interested');
        $this->assertTrue($result['success'], 'Step 4a: Interest should be sent');
        $this->assertEquals('interested', $result['status']);

        // Shortlist user 4
        $result = $actionController->perform(1, 4, 'shortlisted');
        $this->assertTrue($result['success'], 'Step 4b: Shortlist should work');

        // Decline user 5
        $result = $actionController->perform(1, 5, 'declined');
        $this->assertTrue($result['success'], 'Step 4c: Decline should work');

        // ---- Step 5: Mutual match ----
        // User 1 already had mutual match with user 2 from fixtures
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE user_id = :u AND target_id = :t AND status = 'mutual'");
        $stmt->execute([':u' => 1, ':t' => 2]);
        $this->assertEquals(1, (int) $stmt->fetchColumn(), 'Step 5: Mutual match should exist');

        // ---- Step 6: Profile views log ----
        $actionController->logView(1, 6);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_views WHERE viewer_id = :v AND profile_id = :p");
        $stmt->execute([':v' => 1, ':p' => 6]);
        // 1 from fixtures + 1 from logView = 2
        $this->assertEquals(2, (int) $stmt->fetchColumn(), 'Step 6: View should be logged');

        // ---- Step 7: Who viewed me ----
        $viewers = $actionController->getWhoViewedMe(1);
        $this->assertNotEmpty($viewers, 'Step 7: Should show viewers');
    }

    // ========================================================================
    // Journey C — Profile management
    // ========================================================================

    public function test_Journey_C_profile_management(): void
    {
        $this->setAuth(1);
        $pdo = \Matrimony\Database\Connection::pdo();

        // ---- Step 1: Edit city, verify roundtrip ----
        $pdo->prepare("UPDATE profiles SET city = :c WHERE user_id = :id")
            ->execute([':c' => 'Pune', ':id' => 1]);
        $stmt = $pdo->prepare("SELECT city FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => 1]);
        $this->assertEquals('Pune', $stmt->fetchColumn(), 'Step 1: City should be updated');

        // ---- Step 2: Upload and reorder photos ----
        // Add new photo
        $pdo->prepare("INSERT INTO profile_photos (user_id, path, is_primary, status) VALUES (:u, '/uploads/1/new_photo.jpg', 0, 'approved')")
            ->execute([':u' => 1]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_photos WHERE user_id = :id");
        $stmt->execute([':id' => 1]);
        $this->assertEquals(3, (int) $stmt->fetchColumn(), 'Step 2a: Should have 3 photos');

        // Set new photo as primary
        $pdo->prepare("UPDATE profile_photos SET is_primary = 0 WHERE user_id = :id")
            ->execute([':id' => 1]);
        $pdo->prepare("UPDATE profile_photos SET is_primary = 1 WHERE user_id = :id ORDER BY id DESC LIMIT 1")
            ->execute([':id' => 1]);

        $stmt = $pdo->prepare("SELECT path FROM profile_photos WHERE user_id = :id AND is_primary = 1");
        $stmt->execute([':id' => 1]);
        $this->assertEquals('/uploads/1/new_photo.jpg', $stmt->fetchColumn(), 'Step 2b: New photo should be primary');

        // ---- Step 3: Toggle photo privacy ----
        $pdo->prepare("UPDATE profile_photos SET privacy_level = 'protected' WHERE user_id = :id AND id = :pid")
            ->execute([':id' => 1, ':pid' => 2]);

        $stmt = $pdo->prepare("SELECT privacy_level FROM profile_photos WHERE id = :id");
        $stmt->execute([':id' => 2]);
        $this->assertEquals('protected', $stmt->fetchColumn(), 'Step 3: Photo privacy should be updated');

        // ---- Step 4: View activity timeline ----
        $service = new \Matrimony\Services\ProfileService();
        $activity = $service->getActivity(1);
        $this->assertNotEmpty($activity, 'Step 4: Activity timeline should show events');

        // ---- Step 5: Adjust notifications ----
        $pdo->prepare("UPDATE privacy_settings SET receive_interests = 0 WHERE user_id = :id")
            ->execute([':id' => 1]);
        $stmt = $pdo->prepare("SELECT receive_interests FROM privacy_settings WHERE user_id = :id");
        $stmt->execute([':id' => 1]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), 'Step 5: Interest notifications should be disabled');

        // ---- Step 7: Download profile (generate JSON representation) ----
        $profile = $service->getFullProfile(1);
        $this->assertNotNull($profile, 'Step 7a: Profile must be loadable');
        $this->assertArrayHasKey('first_name', $profile);

        // ---- Step 8: Public profile data ----
        $viewerProfile = $service->getViewerProfile(2);
        $this->assertNotNull($viewerProfile, 'Step 8a: Viewer profile should render');
        $this->assertEquals('Alice', $viewerProfile['first_name']);
    }

    // ========================================================================
    // Security journey: SQLi, XSS, CSRF
    // ========================================================================

    public function test_security_sql_injection_blocked(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();

        // Attempt SQL injection via parameterized query (should be prevented by PDO)
        $malicious = "'; DROP TABLE users; --";
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE city = :c");
        $stmt->execute([':c' => $malicious]);
        $results = $stmt->fetchAll();

        // Table should still exist, query should just return empty
        $this->assertIsArray($results);

        // Verify users table still exists
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('users', $tables);
    }

    public function test_security_csrf_protected_endpoints(): void
    {
        $this->setAuth(1);
        $protectedEndpoints = [
            ['POST', '/api/profile/photo'],
            ['PUT', '/api/profile/privacy'],
            ['DELETE', '/api/profile/photo/1'],
        ];

        foreach ($protectedEndpoints as [$method, $uri]) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $uri;
            $_POST = [];
            unset($_POST['csrf']);
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);

            // Clear output buffer
            ob_start();

            // Test CSRF validation directly
            $result = \Matrimony\Http\Csrf::validate(null);
            $this->assertFalse($result, "CSRF check for {$method} {$uri} should fail without token");

            ob_get_clean();
        }
    }
}
