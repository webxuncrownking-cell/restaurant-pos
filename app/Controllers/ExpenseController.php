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
use InvalidArgumentException;
use Throwable;

class ExpenseController
{
    private const CATEGORIES = [
        'utilities' => 'Utilities',
        'rent' => 'Rent',
        'payroll' => 'Payroll',
        'supplies' => 'Operating supplies',
        'repairs' => 'Repairs & maintenance',
        'delivery' => 'Delivery & transport',
        'marketing' => 'Marketing',
        'fees' => 'Bank & service fees',
        'other' => 'Other',
    ];

    public function index(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($restaurantId <= 0) { Response::redirect('/dashboard'); return; }
        $ready = $this->ready();
        $from = $this->validDate((string)$request->get('from', date('Y-m-01'))) ? (string)$request->get('from', date('Y-m-01')) : date('Y-m-01');
        $to = $this->validDate((string)$request->get('to', date('Y-m-d'))) ? (string)$request->get('to', date('Y-m-d')) : date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];
        $expenses = $ready ? Database::fetchAll(
            "SELECT e.*, COALESCE(u.full_name,u.username,'Staff') AS created_by_name
             FROM restaurant_expenses e LEFT JOIN users u ON u.restaurant_id=e.restaurant_id AND u.id=e.created_by
             WHERE e.restaurant_id=? AND e.expense_date BETWEEN ? AND ?
             ORDER BY e.expense_date DESC,e.id DESC LIMIT 500", [$restaurantId,$from,$to]
        ) : [];
        $summary = $ready ? Database::fetchOne(
            "SELECT COALESCE(SUM(amount),0) AS total,COUNT(*) AS entries FROM restaurant_expenses
             WHERE restaurant_id=? AND status='active' AND expense_date BETWEEN ? AND ?", [$restaurantId,$from,$to]
        ) : ['total'=>0,'entries'=>0];
        Response::view('expenses/index', [
            'pageTitle'=>'Operating Expenses','schemaReady'=>$ready,'expenses'=>$expenses,
            'total'=>(float)($summary['total']??0),'entries'=>(int)($summary['entries']??0),
            'categories'=>self::CATEGORIES,'from'=>$from,'to'=>$to,
            'csrf_token'=>CSRF::generate(),'flashes'=>Session::getFlashes()
        ]);
    }

    public function migrate(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger','Security token expired.'); Response::redirect('/expenses'); return; }
        $role=(string)(Auth::roleSlug()??'');
        if (!Auth::isPlatformAdmin() && !in_array($role,['owner','manager'],true)) Response::forbidden('Only an owner or manager can prepare expense tracking.');
        try {
            $pdo = Database::getInstance();
            if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_expenses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    restaurant_id INTEGER NOT NULL,
                    category VARCHAR(40) NOT NULL,
                    description VARCHAR(180) NOT NULL,
                    amount NUMERIC NOT NULL,
                    payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
                    expense_date TEXT NOT NULL,
                    reference_number VARCHAR(80) NULL,
                    notes TEXT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'active',
                    created_by INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    voided_by INTEGER NULL,
                    voided_at TEXT NULL
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_date ON restaurant_expenses(restaurant_id,expense_date,status)");
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS restaurant_expenses (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                restaurant_id INT UNSIGNED NOT NULL,
                category VARCHAR(40) NOT NULL,
                description VARCHAR(180) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payment_method ENUM('cash','card','bank_transfer','qr_online','other') NOT NULL DEFAULT 'cash',
                expense_date DATE NOT NULL,
                reference_number VARCHAR(80) NULL,
                notes TEXT NULL,
                status ENUM('active','voided') NOT NULL DEFAULT 'active',
                created_by INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                voided_by INT UNSIGNED NULL,
                voided_at DATETIME NULL,
                PRIMARY KEY(id),
                KEY idx_expenses_date(restaurant_id,expense_date,status),
                KEY idx_expenses_category(restaurant_id,category),
                CONSTRAINT fk_expenses_restaurant FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT,
                CONSTRAINT fk_expenses_creator FOREIGN KEY(restaurant_id,created_by) REFERENCES users(restaurant_id,id) ON DELETE RESTRICT,
                CONSTRAINT fk_expenses_voider FOREIGN KEY(restaurant_id,voided_by) REFERENCES users(restaurant_id,id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            Session::setFlash('success','Expense tracking is ready.');
        } catch (Throwable $e) { error_log('[Expenses Migration] '.$e); Session::setFlash('danger','Could not prepare expense tracking. Check database permissions and server logs.'); }
        Response::redirect('/expenses');
    }

    public function create(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        $this->requireExpenseManager();
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger','Security token expired.'); Response::redirect('/expenses'); return; }
        $rid=(int)(TenantContext::getRestaurantId()??0); $uid=(int)(Auth::id()??0);
        $category=trim((string)$request->post('category','')); $description=trim((string)$request->post('description',''));
        $amount=$request->post('amount'); $method=trim((string)$request->post('payment_method','cash'));
        $date=trim((string)$request->post('expense_date',date('Y-m-d')));
        $reference=trim((string)$request->post('reference_number','')); $notes=trim((string)$request->post('notes',''));
        try {
            if ($rid<=0||$uid<=0||!$this->ready()) throw new InvalidArgumentException('Expense tracking is not ready.');
            if (!isset(self::CATEGORIES[$category])) throw new InvalidArgumentException('Choose a valid category.');
            if ($description===''||strlen($description)>180) throw new InvalidArgumentException('Enter a description of up to 180 characters.');
            if (!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<=0) throw new InvalidArgumentException('Enter an amount greater than zero.');
            if (!in_array($method,['cash','card','bank_transfer','qr_online','other'],true)) throw new InvalidArgumentException('Choose a valid payment method.');
            if (!$this->validDate($date)||strlen($reference)>80||strlen($notes)>4000) throw new InvalidArgumentException('Check the date, reference number, and notes.');
            Database::query("INSERT INTO restaurant_expenses(restaurant_id,category,description,amount,payment_method,expense_date,reference_number,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?)",
                [$rid,$category,$description,round((float)$amount,2),$method,$date,$reference!==''?$reference:null,$notes!==''?$notes:null,$uid]);
            Session::setFlash('success','Expense recorded.');
        } catch (InvalidArgumentException $e) { Session::setFlash('warning',$e->getMessage()); }
          catch (Throwable $e) { error_log('[Expenses Create] '.$e); Session::setFlash('danger','Unable to record expense. Check the list before retrying.'); }
        Response::redirect('/expenses?from='.rawurlencode($date).'&to='.rawurlencode($date));
    }

    public function void(Request $request): void
    {
        Auth::requirePermission('reports.view_sales');
        $this->requireExpenseManager();
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger','Security token expired.'); Response::redirect('/expenses'); return; }
        $rid=(int)(TenantContext::getRestaurantId()??0); $uid=(int)(Auth::id()??0);
        $id=filter_var($request->post('expense_id'),FILTER_VALIDATE_INT);
        if ($rid<=0||$uid<=0||!$id||$id<=0||!$this->ready()) { Session::setFlash('warning','Choose a valid expense.'); Response::redirect('/expenses'); return; }
        try {
            Database::query(
                "UPDATE restaurant_expenses SET status='voided',voided_by=?,voided_at=CURRENT_TIMESTAMP WHERE restaurant_id=? AND id=? AND status='active'",
                [$uid,$rid,$id]
            );
            Session::setFlash('info','Expense voided if it was active. The record remains in history.');
        } catch (Throwable $e) {
            error_log('[Expenses Void] '.$e);
            Session::setFlash('danger','Unable to void this expense. The record remains in history.');
        }
        Response::redirect('/expenses');
    }

    private function requireExpenseManager(): void
    {
        $role = (string)(Auth::roleSlug() ?? '');
        if (!Auth::isPlatformAdmin() && !in_array($role, ['owner', 'manager'], true)) {
            Response::forbidden('Only an owner or manager can record or void operating expenses.');
        }
    }

    private function ready(): bool
    {
        try { Database::fetchOne("SELECT id FROM restaurant_expenses LIMIT 1"); return true; }
        catch (Throwable $e) { return false; }
    }

    private function validDate(string $value): bool
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        $errors=\DateTimeImmutable::getLastErrors();
        return $date!==false
            && ($errors===false || ((int)$errors['warning_count']===0 && (int)$errors['error_count']===0))
            && $date->format('Y-m-d')===$value;
    }
}
