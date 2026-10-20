<?php
/**
 * Restaurant POS & Management SaaS
 * Active Database Credentials Configuration (MySQL 8+ / MariaDB 10.4+)
 * Default configured for standard XAMPP environment (127.0.0.1:3306, user: root, pass: empty)
 */

declare(strict_types=1);

return [
    'driver'    => getenv('DB_DRIVER') ?: 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => (int)(getenv('DB_PORT') ?: 3306),
    'dbname'    => getenv('DB_NAME') ?: 'restaurant_pos',
    'username'  => getenv('DB_USER') ?: 'root',
    'password'  => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'sqlite_path' => getenv('SQLITE_PATH') ?: (APP_ROOT . '/data/local_test.sqlite'),
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];
