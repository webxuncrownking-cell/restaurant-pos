<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class ScheduledPricingService
{
    public static function findActiveRule(int $restaurantId, int $productId, ?string $at = null): ?array
    {
        if ($restaurantId <= 0 || $productId <= 0) return null;
        $at = $at ?? date('Y-m-d H:i:s');
        $date = substr($at, 0, 10); $time = substr($at, 11, 8); $weekday = (int)date('N', strtotime($at));
        try { $rows = Database::fetchAll("SELECT * FROM menu_price_rules WHERE restaurant_id = ? AND product_id = ? AND is_active = 1 AND (starts_on IS NULL OR starts_on <= ?) AND (ends_on IS NULL OR ends_on >= ?) ORDER BY id DESC", [$restaurantId, $productId, $date, $date]); } catch (\Throwable $e) { return null; }
        foreach ($rows as $rule) {
            $days = array_map('intval', explode(',', (string)($rule['days_of_week'] ?? '1,2,3,4,5,6,7')));
            if (!in_array($weekday, $days, true)) continue;
            $start = substr((string)$rule['starts_at'], 0, 8); $end = substr((string)$rule['ends_at'], 0, 8);
            $within = $start < $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);
            if ($within) return $rule;
        }
        return null;
    }
    public static function calculate(int $restaurantId, int $productId, float $basePrice, ?string $at = null): array
    {
        $rule = self::findActiveRule($restaurantId, $productId, $at);
        if (!$rule) return ['price' => round($basePrice, 2), 'rule_id' => null, 'rule_name' => null];
        $value = (float)$rule['price_value'];
        $price = ($rule['price_type'] ?? 'fixed') === 'percent_off' ? $basePrice * (1 - $value / 100) : $value;
        return ['price' => round(max(0, $price), 2), 'rule_id' => (int)$rule['id'], 'rule_name' => (string)$rule['name']];
    }
}
