<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use Throwable;

final class CustomerController
{
    private function ensureSchema(): void
    {
        $pdo = Database::getConnection();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL,
                full_name TEXT NOT NULL, phone TEXT NULL, email TEXT NULL, birthday TEXT NULL,
                notes TEXT NULL, loyalty_points INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1,
                created_by INTEGER NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NULL, UNIQUE(restaurant_id, phone), UNIQUE(restaurant_id, email)
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_customers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                restaurant_id BIGINT UNSIGNED NOT NULL,
                full_name VARCHAR(160) NOT NULL,
                phone VARCHAR(40) NULL,
                email VARCHAR(190) NULL,
                birthday DATE NULL,
                notes TEXT NULL,
                loyalty_points INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_customer_phone (restaurant_id, phone),
                UNIQUE KEY uq_customer_email (restaurant_id, email),
                KEY idx_customer_name (restaurant_id, full_name),
                CONSTRAINT fk_customer_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    public function index(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        $rid = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($rid <= 0) { Response::redirect('/dashboard'); return; }
        $this->ensureSchema();
        $search = trim((string)$request->get('q', ''));
        $sql = "SELECT id, full_name, phone, email, birthday, notes, loyalty_points, is_active, created_at
                FROM restaurant_customers WHERE restaurant_id = ?";
        $params = [$rid];
        if ($search !== '') {
            $sql .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $needle = '%' . $search . '%';
            array_push($params, $needle, $needle, $needle);
        }
        $sql .= " ORDER BY full_name ASC LIMIT 500";
        $customers = Database::fetchAll($sql, $params);
        $editId = (int)$request->get('edit', 0);
        $editCustomer = $editId > 0
            ? Database::fetchOne("SELECT id, full_name, phone, email, birthday, notes, is_active FROM restaurant_customers WHERE restaurant_id = ? AND id = ? LIMIT 1", [$rid, $editId])
            : null;
        $summary = Database::fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END),0) AS active_count, COALESCE(SUM(loyalty_points),0) AS points FROM restaurant_customers WHERE restaurant_id=?", [$rid]) ?? [];
        $viewData = ['customers'=>$customers,'summary'=>$summary,'search'=>$search,'editCustomer'=>$editCustomer,'flashes'=>Session::getFlashes(),'csrf_token'=>CSRF::getToken(),'title'=>'Customer CRM - ServeFlow POS'];
        http_response_code(200);
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        extract($viewData, EXTR_SKIP);
        require APP_ROOT . '/app/Views/customers/index.php';
    }

    public function save(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger','Security token expired.'); Response::redirect('/customers'); return; }
        $rid = (int)(TenantContext::getRestaurantId() ?? 0);
        $uid = (int)(Auth::id() ?? 0);
        $id = (int)$request->post('id', 0);
        $name = trim((string)$request->post('full_name', ''));
        $phone = trim((string)$request->post('phone', ''));
        $email = strtolower(trim((string)$request->post('email', '')));
        $birthday = trim((string)$request->post('birthday', ''));
        $notes = trim((string)$request->post('notes', ''));
        if ($rid <= 0 || $uid <= 0 || $name === '' || mb_strlen($name) > 160 || strlen($phone) > 40 || strlen($email) > 190 || strlen($notes) > 4000) {
            Session::setFlash('warning','Check the customer name and contact details.'); Response::redirect('/customers'); return;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { Session::setFlash('warning','Enter a valid email address.'); Response::redirect('/customers'); return; }
        if ($birthday !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthday);
            if (!$d || $d->format('Y-m-d') !== $birthday) { Session::setFlash('warning','Enter a valid birthday.'); Response::redirect('/customers'); return; }
        }
        try {
            $phone = $phone === '' ? null : $phone;
            $email = $email === '' ? null : $email;
            $birthday = $birthday === '' ? null : $birthday;
            if ($id > 0) {
                Database::query("UPDATE restaurant_customers SET full_name=?, phone=?, email=?, birthday=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE restaurant_id=? AND id=?", [$name,$phone,$email,$birthday,$notes !== '' ? $notes : null,$rid,$id]);
                Session::setFlash('success','Customer details updated.');
            } else {
                Database::query("INSERT INTO restaurant_customers (restaurant_id,full_name,phone,email,birthday,notes,created_by) VALUES (?,?,?,?,?,?,?)", [$rid,$name,$phone,$email,$birthday,$notes !== '' ? $notes : null,$uid]);
                Session::setFlash('success','Customer added to this restaurant.');
            }
        } catch (Throwable $e) {
            error_log('[Customer CRM] ' . $e);
            Session::setFlash('warning','Could not save the customer. Check for a duplicate phone number or email.');
        }
        Response::redirect('/customers');
    }

    public function toggle(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger','Security token expired.'); Response::redirect('/customers'); return; }
        $rid = (int)(TenantContext::getRestaurantId() ?? 0);
        $id = (int)$request->post('id', 0);
        $active = (int)$request->post('is_active', 0) === 1 ? 1 : 0;
        if ($rid > 0 && $id > 0) {
            Database::query("UPDATE restaurant_customers SET is_active=?, updated_at=CURRENT_TIMESTAMP WHERE restaurant_id=? AND id=?", [$active,$rid,$id]);
            Session::setFlash('success', $active ? 'Customer reactivated.' : 'Customer marked inactive.');
        }
        Response::redirect('/customers');
    }
}
