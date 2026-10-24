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
    public static function view(string $viewPath, array|int $data = [], string|array|int $layout = 'app', int|string|array $status = 200): void
    {
        self::render($viewPath, $data, $layout, $status);
    }

    /**
     * Render a view file with layout wrapping.
     *
     * Supports the current signature render($path, $data, $layout, $status)
     * and the legacy positional signature render($path, $status, $data, $layout).
     * The compatibility handling prevents a view-data array from ever reaching
     * html() as its integer HTTP status argument.
     */
    public static function render(
        string $viewPath,
        array|int $data = [],
        string|array|int $layout = 'app',
        int|string|array $status = 200
    ): void {
        if (is_int($data)) {
            // Legacy order: render($viewPath, $status, $data, $layout).
            $legacyStatus = $data;
            $legacyData = is_array($layout) ? $layout : [];
            $legacyLayout = is_string($status) ? $status : 'app';
            $data = $legacyData;
            $layout = $legacyLayout;
            $status = $legacyStatus;
        } elseif (is_int($layout)) {
            // Also accept render($viewPath, $data, $legacyStatus).
            $status = $layout;
            $layout = 'app';
        } elseif (is_array($layout)) {
            // Invalid layout values fall back safely without affecting the data.
            $layout = 'app';
        }

        if (!is_array($data)) $data = [];
        if (!is_string($layout)) $layout = 'app';
        if (!is_int($status) || $status < 100 || $status > 599) $status = 200;
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

        // Some legacy views include their own header/navbar/footer and therefore
        // already produce a complete HTML document. Do not wrap those a second time.
        $isCompleteDocument = preg_match('/<!doctype\\s+html|<html(?:\\s|>)/i', $content) === 1;
        if ($layout === 'none' || $isCompleteDocument) {
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
