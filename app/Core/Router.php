<?php
/**
 * Restaurant POS & Management SaaS
 * Clean URL & Query-Fallback Router
 */

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function any(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $cleanPath = '/' . trim($path, '/');
        if ($cleanPath !== '/' && str_ends_with($cleanPath, '/')) {
            $cleanPath = rtrim($cleanPath, '/');
        }

        $this->routes[] = [
            'method'  => $method,
            'path'    => $cleanPath,
            'handler' => $handler
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        $matchMethod = ($method === 'HEAD') ? 'GET' : $method;
        foreach ($this->routes as $route) {
            if ($route['method'] !== $matchMethod) {
                continue;
            }

            // Convert route pattern with {param} to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = "#^{$pattern}$#";

            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, '\is_string', ARRAY_FILTER_USE_KEY);
                $this->executeHandler($route['handler'], $request, $params);
                return;
            }
        }

        // No route matched
        Response::notFound("Route [{$method} {$path}] does not exist.");
    }

    private function executeHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            $handler($request, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            if (is_string($class)) {
                if (!class_exists($class)) {
                    Response::html("<h1>Internal Error</h1><p>Controller class '{$class}' not found.</p>", 500);
                }
                $controller = new $class();
            } else {
                $controller = $class;
            }

            if (!method_exists($controller, $action)) {
                Response::html("<h1>Internal Error</h1><p>Method '{$action}' not found on '{$class}'.</p>", 500);
            }

            $controller->$action($request, $params);
            return;
        }

        Response::html("<h1>Internal Error</h1><p>Invalid route handler configured.</p>", 500);
    }
}
