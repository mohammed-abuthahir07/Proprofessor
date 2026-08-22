<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable|array}> */
    private array $routes = [];

    public function get(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = app_base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $params = array_filter(
                $matches,
                static fn($k) => !is_int($k),
                ARRAY_FILTER_USE_KEY
            );
            $this->invoke($route['handler'], $params);
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Not found'], 'layouts/auth');
    }

    private function invoke($handler, array $params): void
    {
        if (is_array($handler) && is_string($handler[0])) {
            $controller = new $handler[0]();
            $method = $handler[1];
            $controller->$method(...array_values($params));
            return;
        }
        if (is_callable($handler)) {
            $handler(...array_values($params));
        }
    }
}
