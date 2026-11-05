<?php
declare(strict_types=1);

namespace App\\Controllers;

use App\\Core\\Auth;
use App\\Core\\CSRF;
use App\\Core\\Database;
use App\\Core\\Request;
use App\\Core\\Response;
use App\\Core\\Session;

class PlatformPricingController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();
        if (!Auth::isPlatformAdmin()) Response::forbidden('Platform administrator access required.');

        $settings = Database::fetchAll("SELECT setting_key, setting_value FROM platform_settings");
        $values = [];
        foreach ($settings as $setting) $values[$setting['setting_key']] = $setting['setting_value'];

        $plans = Database::fetchAll("SELECT id, name, slug, description, price_monthly, price_annually, is_active FROM subscription_plans ORDER BY price_monthly ASC, id ASC");
        Response::render('platform/pricing', [
            'settings' => $values,
            'plans' => $plans,
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
        }

        $activationFee = $request->post('activation_fee');
        $instructions = trim((string)$request->post('payment_instructions', ''));
        $planPrices = $request->post('plans', []);
        if (!is_numeric($activationFee) || (float)$activationFee < 0 || (float)$activationFee > 1000000 || mb_strlen($instructions) > 5000 || !is_array($planPrices)) {
            Session::setFlash('danger', 'Please enter valid pricing and payment instructions.');
            Response::redirect('/platform-pricing');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            self::upsertSetting('activation_fee_php', number_format((float)$activationFee, 2, '.', ''));
            self::upsertSetting('activation_payment_instructions', $instructions);

            foreach ($planPrices as $planId => $price) {
                $id = filter_var($planId, FILTER_VALIDATE_INT);
                if (!$id || !is_array($price)) continue;
                $monthly = $price['monthly'] ?? null;
                $annual = $price['annually'] ?? null;
                if (!is_numeric($monthly) || !is_numeric($annual) || (float)$monthly < 0 || (float)$annual < 0 || (float)$monthly > 1000000 || (float)$annual > 10000000) {
                    throw new \\InvalidArgumentException('One or more subscription plan prices are invalid.');
                }
                Database::query(
                    "UPDATE subscription_plans SET price_monthly = ?, price_annually = ? WHERE id = ?",
                    [number_format((float)$monthly, 2, '.', ''), number_format((float)$annual, 2, '.', ''), $id]
                );
            }
            $pdo->commit();
            Session::setFlash('success', 'Platform activation fee, payment instructions, and subscription prices were saved.');
        } catch (\\Throwable $e) {
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
