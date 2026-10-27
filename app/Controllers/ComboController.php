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
use App\Services\ComboService;
use InvalidArgumentException;
use Throwable;

final class ComboController
{
    public function index(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        if ($restaurantId <= 0) { Response::redirect('/dashboard'); return; }
        ComboService::ensureSchema();
        $products = Database::fetchAll(
            "SELECT p.id, p.name, p.base_price FROM products p
             WHERE p.restaurant_id = ? AND p.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM restaurant_combo_products cp WHERE cp.restaurant_id = p.restaurant_id AND cp.product_id = p.id AND cp.is_active = 1)
             ORDER BY p.name",
            [$restaurantId]
        );
        $componentProducts = Database::fetchAll(
            "SELECT p.id, p.name FROM products p WHERE p.restaurant_id = ? AND p.is_active = 1
             AND NOT EXISTS (SELECT 1 FROM restaurant_combo_products cp WHERE cp.restaurant_id = p.restaurant_id AND cp.product_id = p.id AND cp.is_active = 1)
             AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.restaurant_id = p.restaurant_id AND v.product_id = p.id AND v.is_active = 1)
             AND NOT EXISTS (
                 SELECT 1 FROM product_addon_group_assignments pag
                 JOIN product_addon_groups g ON g.restaurant_id = pag.restaurant_id AND g.id = pag.group_id
                 WHERE pag.restaurant_id = p.restaurant_id AND pag.product_id = p.id
             )
             ORDER BY p.name",
            [$restaurantId]
        );
        $combos = Database::fetchAll(
            "SELECT cp.product_id, p.name AS combo_name, p.base_price, cp.created_at
             FROM restaurant_combo_products cp JOIN products p ON p.restaurant_id = cp.restaurant_id AND p.id = cp.product_id
             WHERE cp.restaurant_id = ? AND cp.is_active = 1 ORDER BY p.name",
            [$restaurantId]
        );
        foreach ($combos as &$combo) {
            $combo['components'] = ComboService::getComponentsForProduct($restaurantId, (int)$combo['product_id']);
        }
        unset($combo);
        Response::render('combos/index', [
            'pageTitle' => 'Combo Meals',
            'products' => $products,
            'componentProducts' => $componentProducts,
            'combos' => $combos,
            'csrf_token' => CSRF::generate(),
            'flashes' => Session::getFlashes()
        ]);
    }

    public function save(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger', 'Security token expired.'); Response::redirect('/combos'); return; }
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $userId = (int)(Auth::id() ?? 0);
        $productId = (int)$request->post('product_id', 0);
        $componentIds = $request->post('component_product_id', []);
        $quantities = $request->post('quantity', []);
        try {
            if (!is_array($componentIds) || !is_array($quantities)) throw new InvalidArgumentException('Choose combo components.');
            ComboService::save($restaurantId, $userId, $productId, $componentIds, $quantities);
            Session::setFlash('success', 'Combo saved. The selected menu item price is the combo selling price.');
        } catch (InvalidArgumentException $e) {
            Session::setFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[Combo Meals] ' . $e);
            Session::setFlash('danger', 'The combo could not be saved. Check the server log.');
        }
        Response::redirect('/combos');
    }

    public function remove(Request $request): void
    {
        Auth::requirePermission('menu.manage_catalog');
        if (!CSRF::validate($request->csrfToken())) { Session::setFlash('danger', 'Security token expired.'); Response::redirect('/combos'); return; }
        $restaurantId = (int)(TenantContext::getRestaurantId() ?? 0);
        $productId = (int)$request->post('product_id', 0);
        try {
            ComboService::remove($restaurantId, $productId);
            Session::setFlash('success', 'Combo definition removed. The menu item remains available at its normal price.');
        } catch (Throwable $e) {
            error_log('[Combo Meals] ' . $e);
            Session::setFlash('danger', 'Unable to remove the combo definition.');
        }
        Response::redirect('/combos');
    }
}
