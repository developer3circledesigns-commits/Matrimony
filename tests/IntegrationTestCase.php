<?php
declare(strict_types=1);

namespace Tests;

abstract class IntegrationTestCase extends TestCase
{
    protected bool $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->usesDatabase) {
            TestDatabase::injectPdo();
            TestDatabase::reset();
            TestDatabase::loadFixtures();
        }
    }

    /**
     * Simulate an API call by setting up server vars and calling a closure.
     */
    protected function apiCall(string $method, string $path, array $data = [], array $params = []): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;

        if ($params) {
            $_SERVER['API_PARAMS'] = $params;
        }

        if ($method === 'POST' || $method === 'PUT') {
            // Set CSRF token if authenticated
            if (!empty($_SESSION['user_id'])) {
                $this->setCsrf();
                $data['csrf'] = $_SESSION['csrf_token'];
            }
            $_POST = $data;
        }

        if ($method === 'GET' && !empty($data)) {
            $_GET = $data;
        }

        if ($method === 'DELETE') {
            if (!empty($_SESSION['user_id'])) {
                $this->setCsrf();
                $_POST['csrf'] = $_SESSION['csrf_token'];
            }
        }

        return $this->capture(function () use ($path) {
            require BASE_PATH . '/public_html/index.php';
        });
    }
}
