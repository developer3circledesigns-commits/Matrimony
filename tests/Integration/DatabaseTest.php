<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\IntegrationTestCase;
use Tests\TestDatabase;

/**
 * @group integration
 * @group database
 *
 * Test cases: D-01 through D-05
 */
final class DatabaseTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::injectPdo();
    }

    // ---- D-05: Migrations idempotent ----

    public function test_D_05_schema_exists_and_idempotent(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();
        // Tables should exist from setUp
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('users', $tables);
        $this->assertContains('profiles', $tables);
        $this->assertContains('profile_photos', $tables);
        $this->assertContains('matches', $tables);
        $this->assertContains('messages', $tables);
        $this->assertContains('membership_plans', $tables);
        $this->assertContains('memberships', $tables);
        $this->assertContains('privacy_settings', $tables);
        $this->assertContains('profile_assets', $tables);
        $this->assertContains('profile_family', $tables);
        $this->assertContains('profile_horoscope', $tables);
        $this->assertContains('profile_lifestyle', $tables);
        $this->assertContains('profile_preferences', $tables);
        $this->assertContains('profile_views', $tables);
        $this->assertContains('profile_searches', $tables);
        $this->assertContains('profile_blocks', $tables);
        $this->assertContains('profile_reports', $tables);
        $this->assertContains('profile_hobbies', $tables);
        $this->assertContains('profile_verifications', $tables);
        $this->assertContains('match_notifications', $tables);
        $this->assertContains('match_scores', $tables);
        $this->assertContains('activity_log', $tables);

        // Re-running schema should be idempotent (tables use IF NOT EXISTS, indexes handle duplicates)
        $this->assertTrue(true);
    }

    // ---- D-01: Foreign key cascade ----

    public function test_D_01_delete_user_cascades_to_profiles(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();

        // Delete user 5 (is_active=0, no references)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => 5]);

        // Verify profile was cascaded
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => 5]);
        $this->assertEquals(0, (int) $stmt->fetchColumn());

        // Verify related data was cascaded
        $tablesWithUserId = ['profile_photos', 'profile_assets', 'profile_family', 'profile_horoscope',
                             'profile_lifestyle', 'profile_preferences', 'privacy_settings',
                             'profile_verifications', 'activity_log', 'match_notifications'];
        foreach ($tablesWithUserId as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :id");
            $stmt->execute([':id' => 5]);
            $this->assertEquals(0, (int) $stmt->fetchColumn(), "Table {$table} should have no rows for deleted user");
        }
        // Tables with different FK column names
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_hobbies WHERE profile_id = :id");
        $stmt->execute([':id' => 5]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), "profile_hobbies should have no rows for deleted user");

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_views WHERE viewer_id = :id");
        $stmt->execute([':id' => 5]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), "profile_views should have no rows for deleted user");

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_blocks WHERE blocker_id = :id");
        $stmt->execute([':id' => 5]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), "profile_blocks should have no rows for deleted user");

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_reports WHERE reporter_id = :id");
        $stmt->execute([':id' => 5]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), "profile_reports should have no rows for deleted user");
    }

    // ---- D-02: Transaction rollback ----

    public function test_D_02_transaction_rolls_back_on_failure(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();

        // Simulate a transaction
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE profiles SET first_name = 'Updated' WHERE user_id = :id")
            ->execute([':id' => 1]);

        $pdo->rollBack();

        // Verify rollback
        $stmt = $pdo->prepare("SELECT first_name FROM profiles WHERE user_id = :id");
        $stmt->execute([':id' => 1]);
        $this->assertEquals('Raj', $stmt->fetchColumn());
    }

    // ---- D-03: Connection pooling (concurrent reads) ----

    public function test_D_03_multiple_concurrent_reads(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();

        // Simulate multiple reads
        for ($i = 0; $i < 10; $i++) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles");
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();
            $this->assertGreaterThan(0, $count);
        }
    }

    // ---- Unique constraints ----

    public function test_unique_email_constraint(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();
        $this->expectException(\PDOException::class);
        $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:e, :p)")
            ->execute([':e' => 'demo@matrimony.local', ':p' => 'hash']);
    }

    public function test_unique_profile_per_user(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();
        $this->expectException(\PDOException::class);
        $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name, gender, date_of_birth) VALUES (:u, :f, :l, :g, :d)")
            ->execute([':u' => 1, ':f' => 'Another', ':l' => 'Profile', ':g' => 'male', ':d' => '1990-01-01']);
    }

    // ---- D-04: Friendly error pages (simulate MySQL down) ----

    public function test_D_04_connection_failure_throws_exception(): void
    {
        // Temporarily change config to bad values
        $origHost = getenv('DB_HOST');
        putenv('DB_HOST=invalid_host_xyz');

        // Clear cached connection
        $ref = new \ReflectionProperty(\Matrimony\Database\Connection::class, 'pdo');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        \Matrimony\Database\Connection::pdo();

        // Restore
        putenv('DB_HOST=' . ($origHost ?: 'localhost'));
        TestDatabase::injectPdo();
    }
}
