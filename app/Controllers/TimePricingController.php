<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;

final class TimePricingController
{
    private function ensureSchema(): void
    {
        $driver = Database::getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            Database::getConnection()->exec("CREATE TABLE IF NOT EXISTS menu_price_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT, restaurant_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
                name TEXT NOT NULL, starts_at TEXT NOT NULL, ends_at TEXT NOT NULL, days_of_week TEXT NOT NULL DEFAULT '1,2,3,4,5,6,7',
                price_type TEXT NOT NULL DEFAULT 'fixed', price_value REAL NOT NULL, starts_on TEXT NULL, ends_on TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1, created_by INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            Database::getConnection()->exec("CREATE TABLE IF NOT EXISTS menu_price_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, starts_at TIME NOT NULL, ends_at TIME NOT NULL,
                days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7', price_type ENUM('fixed','percent_off') NOT NULL DEFAULT 'fixed',
                price_value DECIMAL(12,2) NOT NULL, starts_on DATE NULL, ends_on DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_price_rules_lookup (restaurant_id, product_id, is_active),
                CONSTRAINT fk_price_rules_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
                CONSTRAINT fk_price_rules_product FOREIGN KEY (restaurant_id, product_id) REFERENCES products(restaurant_id, id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }

    public function index(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $restaurantId = TenantContext::getRestaurantId();
        $this->ensureSchema();
        $products = Database::fetchAll("SELECT id, name, base_price FROM products WHERE restaurant_id = ? AND is_active = 1 ORDER BY name", [$restaurantId]);
        $rules = Database::fetchAll("SELECT r.*, p.name AS product_name, p.base_price FROM menu_price_rules r JOIN products p ON p.restaurant_id = r.restaurant_id AND p.id = r.product_id WHERE r.restaurant_id = ? ORDER BY r.is_active DESC, r.starts_at, r.name", [$restaurantId]);
        Response::view('pricing/index', ['pageTitle'=>'Scheduled Menu Pricing','products'=>$products,'rules'=>$rules,'csrf_token'=>CSRF::generate()]);
    }

    public function save(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) { Response::redirect('/pricing?error=Invalid+security+token'); return; }
        $restaurantId = TenantContext::getRestaurantId();
        $this->ensureSchema();
        $name = trim((string)$request->post('name',''));
        $productId = (int)$request->post('product_id',0);
        $starts = trim((string)$request->post('starts_at',''));
        $ends = trim((string)$request->post('ends_at',''));
        $type = (string)$request->post('price_type','fixed');
        $valueRaw = $request->post('price_value','');
        $daysInput = $request->post('days_of_week',[]);
        $days = is_array($daysInput) ? array_values(array_unique(array_filter(array_map('intval',$daysInput), static fn(int $d):bool=>$d>=1&&$d<=7))) : [];
        $startsOn = trim((string)$request->post('starts_on',''));
        $endsOn = trim((string)$request->post('ends_on',''));
        $active = (int)$request->post('is_active',1) === 1 ? 1 : 0;
        if ($name==='' || mb_strlen($name)>120 || $productId<=0 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$starts) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$ends) || $starts===$ends || !is_numeric($valueRaw) || !is_finite((float)$valueRaw) || !in_array($type,['fixed','percent_off'],true) || !$days || ($type==='fixed' && (float)$valueRaw<0) || ($type==='percent_off' && ((float)$valueRaw<=0 || (float)$valueRaw>100))) {
            Response::redirect('/pricing?error=Check+the+rule+name%2C+time%2C+days%2C+product+and+price'); return;
        }
        foreach ([$startsOn,$endsOn] as $date) { if ($date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { Response::redirect('/pricing?error=Invalid+date'); return; } }
        if ($startsOn!=='' && $endsOn!=='' && $startsOn>$endsOn) { Response::redirect('/pricing?error=Start+date+must+not+follow+end+date'); return; }
        $product = Database::fetchOne("SELECT id FROM products WHERE restaurant_id=? AND id=? AND is_active=1",[$restaurantId,$productId]);
        if (!$product) { Response::redirect('/pricing?error=Choose+an+active+menu+item'); return; }
        $driver = Database::getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $dateValue = static fn(string $v): ?string => $v==='' ? null : $v;
        $sql = "INSERT INTO menu_price_rules (restaurant_id,product_id,name,starts_at,ends_at,days_of_week,price_type,price_value,starts_on,ends_on,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $params=[$restaurantId,$productId,$name,$starts,$ends,implode(',',$days),$type,(float)$valueRaw,$dateValue($startsOn),$dateValue($endsOn),$active,Auth::id()];
        if ($driver==='sqlite') { Database::query($sql,$params); } else { Database::query($sql,$params); }
        Response::redirect('/pricing?saved=1');
    }

    public function toggle(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) { Response::redirect('/pricing?error=Invalid+security+token'); return; }
        $restaurantId=TenantContext::getRestaurantId(); $this->ensureSchema();
        $id=(int)$request->post('id',0); $active=(int)$request->post('is_active',0)===1?1:0;
        Database::query("UPDATE menu_price_rules SET is_active=? WHERE restaurant_id=? AND id=?",[$active,$restaurantId,$id]);
        Response::redirect('/pricing?updated=1');
    }
}
