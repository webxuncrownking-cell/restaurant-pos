<?php
/**
 * Restaurant POS & Management SaaS
 * Secure Session Manager with Flash Notifications
 */

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    /**
     * Start secure session if not already started.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Configure session parameters before start
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                session_set_cookie_params([
                    'lifetime' => 86400 * 7, // 7 days
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            @session_start();
        }

        self::$started = true;

        // Auto-initialize CSRF token if missing
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerateId(bool $deleteOldSession = true): bool
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            return session_regenerate_id($deleteOldSession);
        }
        return true;
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            self::$started = false;
        }
    }

    /**
     * Set a flash message for the next request.
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        if (!isset($_SESSION['_flashes'])) {
            $_SESSION['_flashes'] = [];
        }
        $_SESSION['_flashes'][] = [
            'type'    => $type, // 'success', 'danger', 'warning', 'info'
            'message' => $message
        ];
    }

    /**
     * Retrieve and clear all pending flash messages.
     */
    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);
        return $flashes;
    }
}
