<?php
/**
 * Restaurant POS & Management SaaS
 * Cryptographic Anti-CSRF Token Provider & Validator
 */

declare(strict_types=1);

namespace App\Core;

class CSRF
{
    /**
     * Get or generate the session's active CSRF token.
     */
    public static function getToken(): string
    {
        Session::start();
        $token = Session::get('_csrf_token');
        if (!$token || !is_string($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }
        return $token;
    }

    /**
     * Backwards-compatible alias used by the application controllers.
     */
    public static function generate(): string
    {
        return self::getToken();
    }

    /**
     * Regenerate the CSRF token on privilege escalation.
     */
    public static function regenerate(): string
    {
        Session::start();
        $newToken = bin2hex(random_bytes(32));
        Session::set('_csrf_token', $newToken);
        return $newToken;
    }

    /**
     * Validate an incoming token against the stored session token.
     */
    public static function validate(?string $token): bool
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }

        $expected = self::getToken();
        return hash_equals($expected, $token);
    }

    /**
     * Backwards-compatible validator for legacy POST controllers.
     * Invalid or missing tokens terminate the request before any mutation.
     */
    public static function validateToken(?string $token): void
    {
        if (!self::validate($token)) {
            Response::html(
                'Invalid or expired security token. Refresh the page and try again.',
                419
            );
        }
    }

    /**
     * Output a hidden HTML input field containing the CSRF token.
     */
    public static function formField(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Output an HTML meta tag for JavaScript / Fetch API consumption.
     */
    public static function metaHeader(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
