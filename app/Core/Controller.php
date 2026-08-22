<?php
declare(strict_types=1);

namespace App\Core;

use Auth;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(array $data, int $code = 200): void
    {
        json_response($data, $code);
    }

    protected function redirect(string $path): void
    {
        redirect($path);
    }

    protected function requireLogin(): void
    {
        Auth::requireLogin();
    }

    protected function requireRole(string ...$roles): void
    {
        Auth::requireRole(...$roles);
    }

    protected function user(): ?array
    {
        return Auth::user();
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    protected function verifyCsrf(): void
    {
        verify_csrf();
    }

    protected function flash(string $type, string $message): void
    {
        flash($type, $message);
    }
}
