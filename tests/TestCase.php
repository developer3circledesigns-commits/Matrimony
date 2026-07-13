<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSession();
    }

    protected function tearDown(): void
    {
        $this->resetSession();
        $this->resetServerVars();
        parent::tearDown();
    }

    protected function resetSession(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['API_PARAMS']);
    }

    protected function resetServerVars(): void
    {
        foreach (['HTTP_AUTHORIZATION', 'HTTP_X_CSRF_TOKEN', 'API_PARAMS'] as $key) {
            unset($_SERVER[$key]);
        }
    }

    protected function setAuth(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    protected function setCsrf(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function capture(callable $fn): array
    {
        ob_start();
        try {
            $fn();
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }
        $decoded = json_decode($output, true);
        return [
            'body' => $output,
            'json' => $decoded !== null ? $decoded : ['raw' => $output],
        ];
    }

    protected function assertJsonSuccess(array $result): void
    {
        $this->assertTrue($result['success'] ?? false, 'Expected success=true, got: ' . json_encode($result));
    }

    protected function assertJsonError(array $result, int $expectedCode = 400): void
    {
        $this->assertFalse($result['success'] ?? true, 'Expected success=false');
    }

    protected function assertResponseCode(int $expected): void
    {
        $status = http_response_code();
        $this->assertEquals($expected, $status, "Expected HTTP $expected, got $status");
    }

    protected function invokePrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invoke($object, ...$args);
    }

    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }
}
