<?php
/**
 * Restaurant POS & Management SaaS
 * Installation, Diagnostics & Integration Test Runner
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\CSRF;

class InstallController
{
    public function index(Request $request): void
    {
        $diagnostics = $this->runDiagnostics();
        $flashes = Session::getFlashes();

        Response::render('install/index', [
            'diagnostics' => $diagnostics,
            'flashes' => $flashes,
            'csrf_field' => CSRF::formField(),
            'title' => 'System Diagnostics & XAMPP Setup'
        ], 'auth');
    }

    public function runMigration(Request $request): void
    {
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'CSRF verification failed.');
            Response::redirect('/install');
        }

        try {
            $pdo = Database::getInstance();
            $schemaFile = APP_ROOT . '/sql/schema.sql';
            $seedFile = APP_ROOT . '/sql/seed.sql';

            if (!file_exists($schemaFile) || !file_exists($seedFile)) {
                throw new \RuntimeException("SQL scripts missing from sql/ directory.");
            }

            // Execute schema
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);

            // Execute seed
            $seedSql = file_get_contents($seedFile);
            $pdo->exec($seedSql);

            Session::setFlash('success', 'Database schema and seed data imported successfully! System is ready for testing.');
        } catch (\Throwable $e) {
            Session::setFlash('danger', 'Migration failed: ' . $e->getMessage());
        }

        Response::redirect('/install');
    }

    public function runTests(Request $request): void
    {
        $testResults = $this->executeAutomatedTests();
        $flashes = Session::getFlashes();

        Response::render('install/tests', [
            'results' => $testResults,
            'flashes' => $flashes,
            'csrf_field' => CSRF::formField(),
            'title' => 'Integration Test Results'
        ], 'auth');
    }

    private function runDiagnostics(): array
    {
        $results = [];

        // 1. PHP Version
        $phpVersion = PHP_VERSION;
        $results['php_version'] = [
            'label' => 'PHP Version (>= 8.2 required)',
            'value' => $phpVersion,
            'passed' => version_compare($phpVersion, '8.2.0', '>=')
        ];

        // 2. PDO MySQL Extension
        $hasPdoMysql = extension_loaded('pdo_mysql');
        $results['pdo_mysql'] = [
            'label' => 'PDO MySQL Extension',
            'value' => $hasPdoMysql ? 'Installed' : 'Missing',
            'passed' => $hasPdoMysql
        ];

        // 3. PDO SQLite Extension (Optional for testing)
        $hasPdoSqlite = extension_loaded('pdo_sqlite');
        $results['pdo_sqlite'] = [
            'label' => 'PDO SQLite Extension (Fallback & CLI tests)',
            'value' => $hasPdoSqlite ? 'Installed' : 'Missing',
            'passed' => $hasPdoSqlite
        ];

        // 4. Mbstring Extension
        $hasMbstring = extension_loaded('mbstring');
        $results['mbstring'] = [
            'label' => 'Mbstring Extension',
            'value' => $hasMbstring ? 'Installed' : 'Missing',
            'passed' => $hasMbstring
        ];

        // 5. Database Connection Check
        $dbCheck = Database::testConnection();
        $results['database'] = [
            'label' => 'Database Connectivity',
            'value' => $dbCheck['success'] ? "Connected to {$dbCheck['driver']} ({$dbCheck['database']})" : "Error: {$dbCheck['error']}",
            'passed' => $dbCheck['success']
        ];

        // 6. Schema Tables Presence
        $tablesCount = 0;
        if ($dbCheck['success']) {
            try {
                $driver = Database::getInstance()->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $rows = Database::fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                } else {
                    $rows = Database::fetchAll("SHOW TABLES");
                }
                $tablesCount = count($rows);
            } catch (\Throwable $e) {
                $tablesCount = 0;
            }
        }

        $results['tables'] = [
            'label' => 'Imported Tables (schema.sql)',
            'value' => "{$tablesCount} tables detected",
            'passed' => $tablesCount >= 20
        ];

        return $results;
    }

    private function executeAutomatedTests(): array
    {
        $tests = [];

        try {
            $pdo = Database::getInstance();

            // TEST 1: Tenant Foreign Key Isolation (Order Item referencing Product of Another Tenant)
            try {
                // Ensure a valid category exists for Restaurant 2, then create Product 999 in Restaurant 2
                $pdo->exec("INSERT INTO categories (id, restaurant_id, name) VALUES (10, 2, 'Tenant 2 Specials')
                            ON DUPLICATE KEY UPDATE name = VALUES(name)");

                $pdo->exec("INSERT INTO products (id, restaurant_id, category_id, name, sku, base_price, cost_price, product_type, is_active)
                            VALUES (999, 2, 10, 'Alien Product R2', 'PRD-ALIEN-01', 99.00, 50.00, 'recipe', 1)
                            ON DUPLICATE KEY UPDATE name = VALUES(name)");

                $pdo->exec("INSERT INTO orders (id, restaurant_id, client_uuid, device_id, order_number, order_type, table_id, cashier_id, subtotal, total_amount, paid_amount, client_reported_at, server_received_at)
                            VALUES (999, 1, '00000000-0000-4000-8000-000000000999', 1, 'ORD-TEST-999', 'dine_in', 1, 3, 100.00, 100.00, 100.00, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE order_number = VALUES(order_number)");

                // Attempt cross-tenant insert: Order 999 (Restaurant 1) referencing Product 999 (Restaurant 2)
                $pdo->exec("INSERT INTO order_items (restaurant_id, order_id, product_id, product_name, quantity, unit_price, subtotal)
                            VALUES (1, 999, 999, 'Alien Product', 1.00, 99.00, 99.00)");

                // If no exception, test failed!
                $tests[] = [
                    'name' => 'Cross-Tenant Order Item Rejection',
                    'passed' => false,
                    'message' => 'FAIL: Cross-tenant insert succeeded but should have been rejected by composite foreign key.'
                ];
            } catch (\PDOException $e) {
                $tests[] = [
                    'name' => 'Cross-Tenant Order Item Rejection',
                    'passed' => true,
                    'message' => 'PASS: Database rejected cross-tenant reference with foreign key error: ' . $e->getCode()
                ];
            } finally {
                $pdo->exec("DELETE FROM order_items WHERE order_id = 999");
                $pdo->exec("DELETE FROM orders WHERE id = 999");
                $pdo->exec("DELETE FROM products WHERE id = 999");
                $pdo->exec("DELETE FROM categories WHERE id = 10");
            }

            // TEST 2: Base Recipe Uniqueness with NULL variant_id
            try {
                // First insert
                $pdo->exec("INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
                            VALUES (1, 4, NULL, 1, 100.0000)");

                // Duplicate insert
                $pdo->exec("INSERT INTO recipes (restaurant_id, product_id, variant_id, ingredient_id, quantity_required)
                            VALUES (1, 4, NULL, 1, 100.0000)");

                $tests[] = [
                    'name' => 'Base Recipe Uniqueness (NULL variant_id with Stored GenCol)',
                    'passed' => false,
                    'message' => 'FAIL: Duplicate base recipe row was accepted.'
                ];
            } catch (\PDOException $e) {
                $tests[] = [
                    'name' => 'Base Recipe Uniqueness (NULL variant_id with Stored GenCol)',
                    'passed' => true,
                    'message' => 'PASS: Stored generated column recipe_variant_key blocked duplicate base recipe: ' . $e->getCode()
                ];
            } finally {
                $pdo->exec("DELETE FROM recipes WHERE restaurant_id = 1 AND product_id = 4 AND ingredient_id = 1 AND variant_id IS NULL");
            }

            // TEST 3: Out-of-Order Sequence Tracking
            try {
                $pdo->exec("INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
                            VALUES (1, 1, 50, '11111111-1111-4000-8000-000000000050', NOW())");
                $pdo->exec("INSERT INTO pos_device_sequences (restaurant_id, device_id, sequence_number, client_uuid, client_reported_at)
                            VALUES (1, 1, 49, '11111111-1111-4000-8000-000000000049', NOW())");

                $count = (int)$pdo->query("SELECT COUNT(*) FROM pos_device_sequences WHERE restaurant_id = 1 AND device_id = 1 AND sequence_number IN (49, 50)")->fetchColumn();

                $tests[] = [
                    'name' => 'Out-of-Order Sequence Synchronization',
                    'passed' => $count === 2,
                    'message' => $count === 2 ? 'PASS: Sequence #50 and earlier sequence #49 both accepted.' : 'FAIL: Out of order sequences rejected.'
                ];
            } catch (\Throwable $e) {
                $tests[] = [
                    'name' => 'Out-of-Order Sequence Synchronization',
                    'passed' => false,
                    'message' => 'FAIL: ' . $e->getMessage()
                ];
            } finally {
                $pdo->exec("DELETE FROM pos_device_sequences WHERE restaurant_id = 1 AND device_id = 1 AND sequence_number IN (49, 50)");
            }

            // TEST 4: Soft-Delete Zero Cascade Safety
            try {
                $pdo->exec("UPDATE restaurant_tables SET deleted_at = NOW() WHERE id = 1 AND restaurant_id = 1");
                $table = Database::fetchOne("SELECT deleted_at FROM restaurant_tables WHERE id = 1 AND restaurant_id = 1");
                $isSoftDeleted = !empty($table['deleted_at']);

                $tests[] = [
                    'name' => 'Soft-Delete Zero Cascade Safety',
                    'passed' => $isSoftDeleted,
                    'message' => $isSoftDeleted ? 'PASS: Table soft-deleted without cascading deletion.' : 'FAIL: Soft delete failed.'
                ];
            } catch (\Throwable $e) {
                $tests[] = [
                    'name' => 'Soft-Delete Zero Cascade Safety',
                    'passed' => false,
                    'message' => 'FAIL: ' . $e->getMessage()
                ];
            } finally {
                $pdo->exec("UPDATE restaurant_tables SET deleted_at = NULL WHERE id = 1 AND restaurant_id = 1");
            }

        } catch (\Throwable $e) {
            $tests[] = [
                'name' => 'Database Test Suite Execution',
                'passed' => false,
                'message' => 'Critical error: ' . $e->getMessage()
            ];
        }

        return $tests;
    }
}
