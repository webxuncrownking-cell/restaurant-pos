# Restaurant POS & Management SaaS - Phase 1 Setup & XAMPP Testing Guide

This project is built with standard, object-oriented **PHP 8.2+**, **MySQL 8+ / MariaDB 10.4+ (InnoDB engine)**, **PDO with prepared statements**, **Bootstrap 5.3**, and **vanilla JavaScript**. It contains **zero external framework dependencies** (no Laravel, Node.js, React, or cloud SDKs required for production) and is 100% compatible with XAMPP, Apache virtual hosts, and standard cPanel shared hosting.

---

## 1. Quick Requirements
- **PHP**: 8.2 or newer with extensions: `pdo`, `pdo_mysql`, `mbstring` (all included by default in standard XAMPP for Windows/Mac/Linux).
- **Database**: MySQL 8.0+ or MariaDB 10.4+ with InnoDB engine.
- **Web Server**: Apache with `mod_rewrite` enabled (standard in XAMPP).

---

## 2. XAMPP Installation Steps

### Step 1: Copy Project to XAMPP Document Root
Copy this entire project folder into your XAMPP `htdocs` directory:
- **Windows**: `C:\xampp\htdocs\restaurant-pos`
- **macOS**: `/Applications/XAMPP/xamppfiles/htdocs/restaurant-pos`
- **Linux**: `/opt/lampp/htdocs/restaurant-pos`

### Step 2: Start Apache and MySQL
Open the **XAMPP Control Panel** and click **Start** next to:
- **Apache**
- **MySQL**

### Step 3: Create Database & Import Schemas
You can use **phpMyAdmin** or the **MySQL command line**:

#### Option A: Using phpMyAdmin (Browser)
1. Open your browser and navigate to: `http://localhost/phpmyadmin/`
2. Click **New** in the left sidebar.
3. Enter database name: `restaurant_pos`
4. Collation: Select `utf8mb4_unicode_ci` and click **Create**.
5. Select the `restaurant_pos` database.
6. Click the **Import** tab at the top.
7. Click **Choose File**, select `sql/schema.sql`, and click **Import** at the bottom.
8. Click **Import** again, select `sql/seed.sql`, and click **Import**.

#### Option B: Using MySQL Command Line
Open your terminal / command prompt and run:
```bash
# Windows (from C:\xampp\mysql\bin) or Linux/macOS:
mysql -u root -e "CREATE DATABASE restaurant_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root restaurant_pos < sql/schema.sql
mysql -u root restaurant_pos < sql/seed.sql
```

### Step 4: Verify Database Configuration
The application is pre-configured for default XAMPP credentials in `config/database.php`:
```php
'driver'    => 'mysql',
'host'      => '127.0.0.1',
'port'      => 3306,
'dbname'    => 'restaurant_pos',
'username'  => 'root',
'password'  => '', // Default XAMPP root password is blank
```
If your XAMPP MySQL root user has a password, edit `config/database.php` and set `'password' => 'your_password'`.

---

## 3. Accessing the Application

Open your browser and navigate to:
- `http://localhost/restaurant-pos/` (or `http://localhost/` if placed directly in `htdocs`)

### Test Credentials (Pre-seeded):

| Role | Username / Email | Password | Tenant / Context | Offline PIN |
|---|---|---|---|---|
| **Platform Superadmin** | `platform_admin` | `AdminPassword2026!` | Global SaaS Operator | N/A |
| **Tenant 1 Owner** | `juan_admin` | `RestoStaff2026!` | Mang Juan's Carinderia | N/A |
| **Tenant 1 Manager** | `pedro_manager` | `RestoStaff2026!` | Mang Juan's Carinderia | `1234` |
| **Tenant 1 Cashier** | `maria_cashier` | `RestoStaff2026!` | Mang Juan's Carinderia | `1234` |
| **Tenant 2 Owner** | `nena_admin` | `RestoStaff2026!` | Aling Nena's Binalot | N/A |

*Note: Platform Superadmin and first-time accounts have `must_change_password = 1`. Upon first login, you will be prompted to set a new password.*

---

## 4. Running Verification & Integration Tests

### A. Web Diagnostics & In-Browser Test Runner
Navigate in your browser to:
- **System Diagnostics**: `http://localhost/restaurant-pos/install`
  - Validates PHP version, PDO extensions, database connectivity, and imported tables.
- **Integration Test Suite**: `http://localhost/restaurant-pos/install/tests`
  - Runs automated database checks for cross-tenant isolation, composite foreign keys, recipe uniqueness, and sequence ordering.

### B. CLI Integration Test Suite
From the project root folder in terminal / command prompt, run:
```bash
php tests/run_tests.php
```
This runs 21 comprehensive integration checks verifying:
1. PDO connection and MySQL 8+/MariaDB InnoDB support
2. Presence of all 42 tables and relational constraints
3. Strict cross-tenant composite foreign key rejection
4. Stored generated column recipe uniqueness (`recipe_variant_key`)
5. Out-of-order sequence synchronization
6. PBKDF2 offline PIN derivation & verification (100,000 iterations, HMAC-SHA256)
7. Authentication service & bcrypt cost-12 hashing
8. Role-based access control (RBAC) and permission mapping
9. Tenant context & subscription grace period calculation
10. CSRF token generation and validation
11. Zero-cascade soft delete integrity on restaurant tables

### C. Direct MySQL Database Test Suite
Run the raw SQL test suite directly in MySQL/MariaDB:
```bash
mysql -u root restaurant_pos < sql/tests.sql
```
Expected output:
```
Test_Suite: >>> STARTING DATABASE INTEGRATION TEST SUITE <<<
Test_1_Tenant_Isolation: PASS: Database rejected cross-tenant reference with composite foreign key constraint
Test_2_Recipe_Uniqueness: PASS: Stored generated column recipe_variant_key blocked duplicate base recipe (UK uk_recipe_spec)
Test_3_Sequence_Ordering: PASS: Out-of-order sequences #50 and #49 both accepted and persisted
Test_4_Soft_Delete_Integrity: PASS: Table soft-deleted without cascading deletion to orders or shifts
Test_Summary: >>> ALL DATABASE INTEGRATION TESTS EXECUTED SUCCESSFULLY <<<
```

---

## 5. Phase 1 Architecture & Security Highlights

1. **100% Tenant-Safe Composite Foreign Keys**:
   - `order_items` references `(restaurant_id, order_id)` and `(restaurant_id, product_id)`. Cross-restaurant product assignments are rejected at the database engine level.
   - `orders` references `(restaurant_id, table_id)` with `ON DELETE RESTRICT`, preventing accidental loss of order history when tables are deleted.
   - `orders` references `(restaurant_id, shift_id)` with `ON DELETE RESTRICT`.
   - `platform_invoices` references `(restaurant_id, subscription_id)` and `platform_payments` references `(restaurant_id, invoice_id)`.
   - Audit logs, void records, refund records, and inventory conflicts strictly enforce `(restaurant_id, user_id)`.

2. **Base Recipe Bill of Materials (BOM) Uniqueness**:
   - Uses a stored generated column `recipe_variant_key AS (COALESCE(variant_id, 0)) STORED` with unique index `(restaurant_id, product_id, recipe_variant_key, ingredient_id)`. This prevents duplicate base recipes while allowing full compatibility across MySQL 8.0+ and MariaDB 10.4+.

3. **Offline Security Foundation**:
   - Uses **PBKDF2-HMAC-SHA256 with 100,000 iterations** and a unique 16-byte cryptographic salt per user.
   - Staff PIN hashes are scoped per device and never transmitted to unauthorized terminals.

4. **Subscription Grace Period Engine**:
   - Computes grace period expiration accurately, preventing new sales once the grace window closes while protecting and synchronizing valid sales made during the authorized period.
