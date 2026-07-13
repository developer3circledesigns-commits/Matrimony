<?php
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function partial(string $name, array $vars = []): void
{
    $file = BASE_PATH . '/partials/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Partial not found: {$name}");
    }
    extract($vars, EXTR_SKIP);
    require $file;
}
