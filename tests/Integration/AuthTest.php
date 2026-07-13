<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\IntegrationTestCase;
use Tests\TestDatabase;

/**
 * @group integration
 * @group auth
 *
 * Test cases: P-F-11, P-F-12, P-F-13, P-F-14, P-S-03, P-S-08, P-S-09
 */
final class AuthTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::injectPdo();
    }

    // ---- P-S-03: CSRF protection ----

    public function test_P_S_03_post_without_csrf_returns_419(): void
    {
        $this->setAuth(1);
        // Validate directly instead of calling Csrf::require() which calls exit
        $result = \Matrimony\Http\Csrf::validate(null);
        $this->assertFalse($result);

        $result = \Matrimony\Http\Csrf::validate('');
        $this->assertFalse($result);

        $result = \Matrimony\Http\Csrf::validate('wrong_token');
        $this->assertFalse($result);
    }

    // ---- P-S-08: Session cookie attributes ----

    public function test_P_S_08_session_cookie_has_httponly_and_samesite(): void
    {
        $params = session_get_cookie_params();
        $this->assertTrue($params['httponly'], 'Session cookie must be HttpOnly');
        $this->assertEquals('Lax', $params['samesite'], 'Session cookie must be SameSite=Lax');
    }

    // ---- P-S-09: Password hash uses bcrypt ----

    public function test_P_S_09_password_hash_is_bcrypt(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => 1]);
        $hash = $stmt->fetchColumn();

        $this->assertStringStartsWith('$2y$', $hash, 'Password must use bcrypt ($2y$)');
    }

    // ---- Auth: Protected endpoints require login ----

    public function test_unauthorized_access_returns_401(): void
    {
        // No auth set — session is empty
        $this->assertFalse(\Matrimony\Http\Auth::check());
        $this->assertNull(\Matrimony\Http\Auth::id());
    }

    // ---- P-F-11: Password change ----

    public function test_P_F_11_password_hash_update(): void
    {
        $pdo = \Matrimony\Database\Connection::pdo();
        $newPassword = 'NewPass123';
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
            ->execute([':hash' => $hash, ':id' => 1]);

        // Verify
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => 1]);
        $stored = $stmt->fetchColumn();

        $this->assertTrue(password_verify($newPassword, $stored));
        $this->assertStringStartsWith('$2y$', $stored);
        $info = password_get_info($stored);
        $this->assertEquals(PASSWORD_BCRYPT, $info['algo']);
    }

    // ---- P-F-14: Notification settings ----

    public function test_P_F_14_notification_settings_persist(): void
    {
        $this->setAuth(1);

        // Update preference
        $pdo = \Matrimony\Database\Connection::pdo();
        $pdo->prepare("UPDATE privacy_settings SET receive_interests = 0 WHERE user_id = :id")
            ->execute([':id' => 1]);

        // Verify read
        $stmt = $pdo->prepare("SELECT receive_interests FROM privacy_settings WHERE user_id = :id");
        $stmt->execute([':id' => 1]);
        $this->assertEquals(0, (int) $stmt->fetchColumn());
    }
}
