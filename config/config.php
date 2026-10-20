<?php
/**
 * Restaurant POS & Management SaaS
 * Global Application Configuration
 * Compatible with PHP 8.2+, XAMPP and cPanel Shared Hosting
 */

declare(strict_types=1);

// Prevent direct script execution if requested
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Application Metadata
define('APP_NAME', 'Restaurant POS & Management SaaS');
define('APP_VERSION', '1.0.0-phase1');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // 'development' or 'production'

// Default Timezone (Philippine Standard Time)
date_default_timezone_set('Asia/Manila');

// Error Reporting Configuration
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Session Security Configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

// Automatic Base URL Detection (Supports XAMPP subdirectories e.g. /restaurant-pos/)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine path relative to document root
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
    
    define('BASE_URL', $protocol . $host . $basePath);
    define('BASE_PATH', $basePath);
}
