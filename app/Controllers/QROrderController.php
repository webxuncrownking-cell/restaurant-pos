<?php
/**
 * Restaurant POS & Management SaaS
 * Public QR table menu and unpaid dine-in order submission.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\DeviceService;
use App\Services\MenuService;
use App\Services\PosSyncService;
use App\Services\QROrderTrackingService;
use App\Services\TableQrService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class QROrderController
{
    public function menu(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        $table = TableQrService::getTableByToken($token);
        if (!$table) {
            Response::notFound('This table QR link is invalid or no longer active.');
            return;
        }
        $this->renderMenuPage($request, $token, $table);
    }

    public function submit(Request $request, array $params): void
    {
        $token = strtolower(trim((string)($params['token'] ?? '')));
        $table = TableQrService::getTableByToken($token);
        if (!$table) {
            Response::notFound('This table QR link is invalid or no longer active.');
            return;
        }

        if (!CSRF::validate($request->csrfToken() ?? (string)$request->post('csrf_token', ''))) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Your session expired. Refresh the menu and try again.'));
            return;
        }
        if (($table['table_status'] ?? '') === 'cleaning') {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('This table is being prepared. Please ask a staff member for help.'));
            return;
        }

        $customerName = trim((string)$request->post('customer_name', ''));
        $customerNote = trim((string)$request->post('customer_note', ''));
        $clientUuid = strtolower(trim((string)$request->post('client_uuid', '')));
        $itemsJson = (string)$request->post('items_json', '');

        if ($customerName === '' || mb_strlen($customerName) > 120) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Please enter your name (up to 120 characters).'));
            return;
        }
        if (mb_strlen($customerNote) > 500) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Order note must be 500 characters or fewer.'));
            return;
        }
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $clientUuid)) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Refresh the menu before submitting the order.'));
            return;
        }

        $itemsInput = json_decode($itemsJson, true);
        if (!is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 100) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Add at least one menu item before submitting.'));
            return;
        }

        $items = [];
        foreach ($itemsInput as $itemInput) {
            if (!is_array($itemInput)) {
                Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('One of the selected items is invalid.'));
                return;
            }
            $productId = filter_var($itemInput['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantityRaw = $itemInput['quantity'] ?? null;
            if (!$productId || $productId < 1 || !is_numeric($quantityRaw) || !is_finite((float)$quantityRaw)
                || (float)$quantityRaw < 1 || (float)$quantityRaw > 50 || floor((float)$quantityRaw) !== (float)$quantityRaw) {
                Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Check the selected items and quantities.'));
                return;
            }

            $variantIdRaw = $itemInput['variant_id'] ?? null;
            $variantId = $variantIdRaw === null || $variantIdRaw === '' ? null : filter_var($variantIdRaw, FILTER_VALIDATE_INT);
            if ($variantId === false || ($variantId !== null && $variantId < 1)) {
                Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('One of the selected serving options is invalid.'));
                return;
            }

            $addonInput = $itemInput['addons'] ?? [];
            if (!is_array($addonInput) || count($addonInput) > 50) {
                Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('One of the add-on selections is invalid.'));
                return;
            }
            $addonIds = [];
            foreach ($addonInput as $addonIdRaw) {
                $addonId = filter_var($addonIdRaw, FILTER_VALIDATE_INT);
                if (!$addonId || $addonId < 1 || in_array((int)$addonId, $addonIds, true)) {
                    Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('One of the add-on selections is invalid.'));
                    return;
                }
                $addonIds[] = (int)$addonId;
            }

            $instructions = trim((string)($itemInput['special_instructions'] ?? ''));
            if (mb_strlen($instructions) > 255) {
                Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Item instructions must be 255 characters or fewer.'));
                return;
            }

            $items[] = [
                'product_id' => (int)$productId,
                'variant_id' => $variantId === null ? null : (int)$variantId,
                'quantity' => (float)$quantityRaw,
                'addons' => array_map(static fn(int $id): array => ['id' => $id], $addonIds),
                'special_instructions' => $instructions
            ];
        }

        $restaurantId = (int)$table['restaurant_id'];
        $device = Database::fetchOne(
            "SELECT id FROM pos_devices WHERE restaurant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1",
            [$restaurantId]
        );
        $cashier = Database::fetchOne(
            "SELECT id FROM users
             WHERE restaurant_id = ? AND status = 'active' AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 1",
            [$restaurantId]
        );
        if (!$device || !$cashier) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('Online ordering is not ready yet. Please ask staff to configure an active POS device and staff account.'));
            return;
        }

        $notes = 'QR table order · Guest: ' . $customerName;
        if ($customerNote !== '') $notes .= ' · Note: ' . $customerNote;
        try {
            QROrderTrackingService::ensureSchema();
            $result = PosSyncService::processOrderSync($restaurantId, [
                'client_uuid' => $clientUuid,
                'device_id' => (int)$device['id'],
                'cashier_id' => (int)$cashier['id'],
                'order_type' => 'dine_in',
                'table_id' => (int)$table['table_id'],
                'save_as_open' => true,
                'require_addon_groups' => true,
                'discount_amount' => 0,
                'notes' => $notes,
                'items' => $items,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $orderNumber = (string)($result['order_number'] ?? '');
            $orderId = (int)($result['server_order_id'] ?? 0);
            if ($orderId < 1) {
                throw new RuntimeException('The order was submitted, but its status link could not be prepared.');
            }
            $trackingToken = QROrderTrackingService::createTrackingToken($restaurantId, $orderId, (int)$table['table_id']);
            Response::redirect('/order/' . rawurlencode($token) . '/track/' . rawurlencode($trackingToken));
        } catch (InvalidArgumentException $e) {
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode($e->getMessage()));
        } catch (Throwable $e) {
            error_log('[QR Table Ordering] ' . $e);
            Response::redirect('/order/' . rawurlencode($token) . '?error=' . rawurlencode('The order could not be submitted. Please check your selections or ask staff for help.'));
        }
    }

    private function renderMenuPage(Request $request, string $token, array $table): void
    {
        $restaurantId = (int)$table['restaurant_id'];
        $categories = MenuService::getCategories($restaurantId, true);
        $activeCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);
        $products = MenuService::getProducts($restaurantId, null, true);
        $products = array_values(array_filter($products, static fn(array $product): bool =>
            in_array((int)$product['category_id'], $activeCategoryIds, true)
        ));
        $publicProducts = [];
        foreach ($products as $product) {
            $image = trim((string)($product['image_path'] ?? ''));
            $publicVariants = [];
            foreach (($product['variants'] ?? []) as $variant) {
                $publicVariants[] = [
                    'id' => (int)$variant['id'],
                    'name' => (string)$variant['name'],
                    'additional_price' => (float)$variant['additional_price']
                ];
            }
            $publicAddonGroups = [];
            foreach (($product['addon_groups'] ?? []) as $group) {
                $publicOptions = [];
                foreach (($group['options'] ?? []) as $option) {
                    $publicOptions[] = [
                        'id' => (int)$option['id'],
                        'name' => (string)$option['name'],
                        'price' => (float)$option['price']
                    ];
                }
                $publicAddonGroups[] = [
                    'id' => (int)$group['id'],
                    'name' => (string)$group['name'],
                    'is_required' => (int)($group['is_required'] ?? 0),
                    'options' => $publicOptions
                ];
            }
            $publicProducts[] = [
                'id' => (int)$product['id'],
                'category_id' => (int)$product['category_id'],
                'category_name' => (string)$product['category_name'],
                'name' => (string)$product['name'],
                'base_price' => (float)$product['base_price'],
                'image_url' => $image !== '' ? BASE_URL . '/' . ltrim($image, '/') : '',
                'variants' => $publicVariants,
                'addon_groups' => $publicAddonGroups
            ];
        }

        Response::render('qr_order/menu', [
            'table' => $table,
            'categories' => $categories,
            'products' => $publicProducts,
            'csrf_token' => CSRF::getToken(),
            'client_uuid' => DeviceService::generateUuidV4(),
            'qr_token' => $token,
            'error_message' => trim((string)$request->get('error', '')),
            'submitted_order_number' => trim((string)$request->get('submitted', ''))
        ], 'none');
    }
}
