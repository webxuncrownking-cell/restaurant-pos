<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class PlatformPricingController
{
    private const FEATURES = [
        'advanced_reports' => 'Advanced analytics & reports',
        'customer_crm' => 'Customer CRM',
        'loyalty' => 'Customer loyalty',
        'combo_meals' => 'Combo meals',
        'scheduled_pricing' => 'Scheduled menu pricing',
        'reservations' => 'Reservations & waitlist',
        'expenses' => 'Expense tracking',
        'suppliers' => 'Supplier management',
        'purchase_orders' => 'Purchase orders',
        'recipe_costing' => 'Recipe costing',
        'expiry_wastage' => 'Expiry & wastage tracking',
        'multi_terminal' => 'Multi-terminal management',
        'api_access' => 'API access',
    ];

    public function index(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');

        $settings = Database::fetchAll("SELECT setting_key, setting_value FROM platform_settings");
        $values = [];
        foreach ($settings as $setting) $values[$setting['setting_key']] = $setting['setting_value'];

        $plans = Database::fetchAll("SELECT id, name, slug, description, price_monthly, price_annually, features_json, is_active FROM subscription_plans ORDER BY price_monthly ASC, id ASC");
        foreach ($plans as &$plan) {
            $decoded = json_decode((string)($plan['features_json'] ?? '{}'), true);
            $plan['features'] = is_array($decoded) ? $decoded : [];
        }
        unset($plan);

        Response::render('platform/pricing', [
            'settings' => $values,
            'plans' => $plans,
            'featureCatalog' => self::FEATURES,
            'flashes' => Session::getFlashes()
        ], 'app');
    }

    public function save(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');
        if (!CSRF::validate($request->post('csrf_token'))) {
            Session::setFlash('danger', 'Security token expired. Refresh and try again.');
            Response::redirect('/platform-pricing');
            return;
        }

        $activationFee = $request->post('activation_fee');
        $instructions = trim((string)$request->post('payment_instructions', ''));
        $planPrices = $request->post('plans', []);
        if (!is_numeric($activationFee) || (float)$activationFee < 0 || (float)$activationFee > 1000000 || mb_strlen($instructions) > 5000 || !is_array($planPrices)) {
            Session::setFlash('danger', 'Please enter valid pricing and payment instructions.');
            Response::redirect('/platform-pricing');
            return;
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            self::upsertSetting('activation_fee_php', number_format((float)$activationFee, 2, '.', ''));
            self::upsertSetting('activation_payment_instructions', $instructions);

            $existingPlans = Database::fetchAll("SELECT id, features_json FROM subscription_plans");
            $existingById = [];
            foreach ($existingPlans as $row) $existingById[(int)$row['id']] = $row;

            foreach ($planPrices as $planId => $price) {
                $id = filter_var($planId, FILTER_VALIDATE_INT);
                if (!$id || !isset($existingById[$id]) || !is_array($price)) {
                    throw new \InvalidArgumentException('An unknown subscription plan was submitted.');
                }
                $monthly = $price['monthly'] ?? null;
                $annual = $price['annually'] ?? null;
                $maxStaff = $price['max_staff'] ?? null;
                $submittedFeatures = $price['features'] ?? [];
                if (!is_array($submittedFeatures) || !is_numeric($monthly) || !is_numeric($annual)
                    || !filter_var($maxStaff, FILTER_VALIDATE_INT) && (string)$maxStaff !== '0'
                    || (int)$maxStaff < 0 || (int)$maxStaff > 5000
                    || (float)$monthly < 0 || (float)$annual < 0
                    || (float)$monthly > 1000000 || (float)$annual > 10000000) {
                    throw new \InvalidArgumentException('One or more subscription plan prices or features are invalid.');
                }

                $features = json_decode((string)$existingById[$id]['features_json'], true);
                if (!is_array($features)) $features = [];
                // Preserve legacy/other flags, while explicitly writing every managed
                // ServeFlow entitlement so an unchecked box reliably revokes access.
                foreach (array_keys(self::FEATURES) as $featureKey) {
                    $features[$featureKey] = isset($submittedFeatures[$featureKey]) && (string)$submittedFeatures[$featureKey] === '1';
                }

                Database::query(
                    "UPDATE subscription_plans SET price_monthly = ?, price_annually = ?, max_staff = ?, features_json = ? WHERE id = ?",
                    [
                        number_format((float)$monthly, 2, '.', ''),
                        number_format((float)$annual, 2, '.', ''),
                        (int)$maxStaff,
                        json_encode($features, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        $id
                    ]
                );
            }

            $pdo->commit();
            Session::setFlash('success', 'Activation fee, payment instructions, plan prices, and plan features were saved.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[ServeFlow platform pricing] ' . $e->getMessage());
            Session::setFlash('danger', 'Settings were not saved. Check the entered values and try again.');
        }
        Response::redirect('/platform-pricing');
    }

    private static function upsertSetting(string $key, string $value): void
    {
        Database::query(
            "INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
    }
}
