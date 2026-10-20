<?php
/**
 * Restaurant POS & Management SaaS
 * Database Connection & Transaction Manager (PDO Singleton)
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     */
    private function __construct() {}

    /**
     * Retrieve the shared PDO database connection instance.
     *
     * @return PDO
     * @throws RuntimeException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Alias for getInstance() for semantic readability.
     */
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }

    /**
     * Establish the PDO connection using configuration in config/database.php.
     */
    private static function connect(): void
    {
        $configFile = APP_ROOT . '/config/database.php';
        if (!file_exists($configFile)) {
            throw new RuntimeException("Database configuration file not found at: {$configFile}. Please copy config/database.example.php to config/database.php.");
        }

        self::$config = require $configFile;
        $driver = self::$config['driver'] ?? 'mysql';

        try {
            if ($driver === 'sqlite') {
                $dbPath = self::$config['sqlite_path'] ?? (APP_ROOT . '/data/local_test.sqlite');
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $dsn = "sqlite:{$dbPath}";
                self::$instance = new PDO($dsn, null, null, self::$config['options'] ?? []);
                self::$instance->exec("PRAGMA foreign_keys = ON;");
            } else {
                $host = self::$config['host'] ?? '127.0.0.1';
                $port = self::$config['port'] ?? 3306;
                $dbname = self::$config['dbname'] ?? 'restaurant_pos';
                $charset = self::$config['charset'] ?? 'utf8mb4';

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                $username = self::$config['username'] ?? 'root';
                $password = self::$config['password'] ?? '';
                $options = self::$config['options'] ?? [];

                // Enforce strict UTF-8 & Strict SQL Modes on MySQL / MariaDB
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE " . (self::$config['collation'] ?? 'utf8mb4_unicode_ci') . ", sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'";

                self::$instance = new PDO($dsn, $username, $password, $options);
            }
        } catch (PDOException $e) {
            $msg = "Database Connection Failed: " . $e->getMessage();
            if (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'Access denied')) {
                $msg .= "\n[XAMPP Troubleshooting Note]: Ensure MySQL is running in the XAMPP Control Panel, database '{$dbname}' exists, and credentials in config/database.php match.";
            }
            throw new RuntimeException($msg, (int)$e->getCode(), $e);
        }
    }

    /**
     * Start a new ACID transaction.
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit the active transaction.
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback the active transaction if one exists.
     */
    public static function rollBack(): bool
    {
        if (self::inTransaction()) {
            return self::getInstance()->rollBack();
        }
        return false;
    }

    /**
     * Check if currently inside an uncommitted transaction.
     */
    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }

    /**
     * Helper to safely execute a prepared query with bound parameters.
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Helper to fetch all records matching prepared statement.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Helper to fetch a single record matching prepared statement.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $res = self::query($sql, $params)->fetch();
        return $res ?: null;
    }

    /**
     * Helper to fetch a single column from the first row matching prepared statement.
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return self::query($sql, $params)->fetchColumn($column);
    }

    /**
     * Get the ID of the last inserted row.
     */
    public static function lastInsertId(?string $name = null): string
    {
        return self::getInstance()->lastInsertId($name);
    }

    /**
     * Test if the database connection can be established without throwing fatal unhandled errors.
     */
    public static function testConnection(): array
    {
        try {
            $pdo = self::getInstance();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            return [
                'success' => true,
                'driver' => $driver,
                'server_version' => $serverVersion,
                'database' => self::$config['dbname'] ?? 'sqlite'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
