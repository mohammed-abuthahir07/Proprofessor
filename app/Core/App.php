<?php
declare(strict_types=1);

namespace App\Core;

use Auth;

final class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        Auth::start();
        $routes = dirname(__DIR__, 2) . '/routes/web.php';
        $router = $this->router;
        require $routes;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->router->dispatch($method, $uri);
    }
}
