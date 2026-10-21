<?php
/**
 * Restaurant POS & Management SaaS
 * HTTP Response Handler & View Renderer
 */

declare(strict_types=1);

namespace App\Core;

class Response
{
    /**
     * Send a JSON response with status code.
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an HTML response.
     */
    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Redirect to another URL or application path.
     */
    public static function redirect(string $path): void
    {
        // If path is relative to app, prepend BASE_URL
        if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
            $cleanPath = '/' . ltrim($path, '/');
            $url = BASE_URL . $cleanPath;
        } else {
            $url = $path;
        }

        header("Location: {$url}");
        exit;
    }

    /**
     * Backwards-compatible alias for controllers using Response::view().
     */
    public static function view(string $viewPath, array $data = [], string $layout = 'app', int $status = 200): void
    {
        self::render($viewPath, $data, $layout, $status);
    }

    /**
     * Render a view file with layout wrapping.
     */
    public static function render(string $viewPath, array $data = [], string $layout = 'app', int $status = 200): void
    {
        $viewFile = APP_ROOT . '/app/Views/' . ltrim($viewPath, '/') . '.php';
        if (!file_exists($viewFile)) {
            self::html("<h1>View Not Found</h1><p>Missing view file: {$viewPath}.php</p>", 500);
        }

        // Extract variables to local scope
        extract($data, EXTR_SKIP);

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === 'none') {
            self::html($content, $status);
            return;
        }

        // Render standard layout
        $headerFile = APP_ROOT . '/app/Views/layouts/header.php';
        $navbarFile = APP_ROOT . '/app/Views/layouts/navbar.php';
        $footerFile = APP_ROOT . '/app/Views/layouts/footer.php';

        ob_start();
        if (file_exists($headerFile)) require $headerFile;
        if ($layout !== 'auth' && file_exists($navbarFile)) require $navbarFile;
        
        echo $content;
        
        if (file_exists($footerFile)) require $footerFile;
        $fullOutput = ob_get_clean();

        self::html($fullOutput, $status);
    }

    public static function notFound(string $message = 'The requested resource was not found.'): void
    {
        self::render('errors/404', ['message' => $message], 'auth', 404);
    }

    public static function forbidden(string $message = 'You do not have permission to access this resource.'): void
    {
        self::render('errors/403', ['message' => $message], 'auth', 403);
    }
}
