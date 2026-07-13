<?php
/**
 * Base controller — shared helpers for all controllers.
 * Extend this for feature-specific controllers under /modules.
 */
namespace Matrimony\Http;

abstract class Controller
{
    /**
     * Render a view from /modules/<feature>/views/<view>.php
     */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/modules/' . $view . '.php';
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . '/public_html/partials/layouts/' . $layout . '.php';
        if (is_file($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Issue an HTTP redirect.
     */
    protected function redirect(string $to, int $status = 302): void
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    /**
     * Send a JSON response.
     */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
