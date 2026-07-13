<?php
declare(strict_types=1);

namespace Tests\Unit;

use Matrimony\Database\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Tests\TestCase;

final class ActionControllerTest extends TestCase
{
    public function test_perform_invalid_status_returns_error(): void
    {
        $controller = $this->createActionController();
        $result = $controller->perform(1, 2, 'invalid_status');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid action', $result['error']);
    }

    public function test_perform_self_action_returns_error(): void
    {
        $controller = $this->createActionController();
        $result = $controller->perform(1, 1, 'interested');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot act on yourself', $result['error']);
    }

    public function test_block_self_returns_error(): void
    {
        $controller = $this->createActionController();
        $result = $controller->block(1, 1);
        $this->assertFalse($result['success']);
    }

    public function test_report_empty_reason_returns_error(): void
    {
        $controller = $this->createActionController();
        $result = $controller->report(1, 2, '');
        $this->assertFalse($result['success']);
    }

    public function test_report_with_reason_succeeds(): void
    {
        // Mock PDO for this test
        $pdoMock = $this->createMock(\PDO::class);
        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $pdoMock->method('prepare')->willReturn($stmtMock);

        // Use reflection to set the static PDO
        $ref = new \ReflectionProperty(Connection::class, 'pdo');
        $ref->setAccessible(true);
        $ref->setValue(null, $pdoMock);

        $controller = $this->createActionController();
        $result = $controller->report(1, 2, 'Fake profile');
        $this->assertTrue($result['success']);
    }

    private function createActionController(): \ActionController
    {
        require_once BASE_PATH . '/modules/matches/controllers/ActionController.php';
        return new \ActionController();
    }
}
