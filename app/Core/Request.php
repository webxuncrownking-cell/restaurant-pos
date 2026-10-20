<?php
/**
 * Restaurant POS & Management SaaS
 * HTTP Request Abstraction & Sanitizer
 */

declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $getParams;
    private array $postParams;
    private ?array $jsonBody = null;
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->getParams = $_GET;
        $this->postParams = $_POST;
        $this->headers = $this->captureHeaders();
        $this->path = $this->resolvePath();
    }

    private function captureHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    private function resolvePath(): string
    {
        // Check for query parameter fallback first (e.g. index.php?page=login)
        if (!empty($_GET['page'])) {
            return '/' . trim((string)$_GET['page'], '/');
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uriPath = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip basePath if running in a subdirectory (e.g. /restaurant-pos)
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($uriPath, BASE_PATH)) {
            $uriPath = substr($uriPath, strlen(BASE_PATH));
        }

        // Clean index.php from path
        if (str_starts_with($uriPath, '/index.php')) {
            $uriPath = substr($uriPath, 10);
        }

        $cleanPath = '/' . trim($uriPath, '/');
        return $cleanPath === '' ? '/' : $cleanPath;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getParams[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->isJson()) {
            return array_merge($this->getParams, $this->json());
        }
        return array_merge($this->getParams, $this->postParams);
    }

    public function isJson(): bool
    {
        $contentType = $this->header('content-type', '');
        return str_contains($contentType, 'application/json');
    }

    public function json(): array
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest' || $this->isJson();
    }

    public function csrfToken(): ?string
    {
        return $this->post('csrf_token') 
            ?? $this->header('x-csrf-token') 
            ?? ($this->json()['csrf_token'] ?? null);
    }
}
