<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class ScheduledPricingService
{
    private static array $timezoneCache = [];

    public static function findActiveRule(int $restaurantId, int $productId, ?string $at = null): ?array
    {
        if ($restaurantId <= 0 || $productId <= 0) return null;
        try {
            if (!isset(self::$timezoneCache[$restaurantId])) {
                $timezoneName = (string)(Database::fetchColumn(
                    "SELECT timezone FROM restaurants WHERE id = ? LIMIT 1",
                    [$restaurantId]
                ) ?: 'Asia/Manila');
                try { self::$timezoneCache[$restaurantId] = new \DateTimeZone($timezoneName); }
                catch (\Throwable $ignored) { self::$timezoneCache[$restaurantId] = new \DateTimeZone('Asia/Manila'); }
            }
            $timezone = self::$timezoneCache[$restaurantId];
            $moment = $at !== null
                ? new \DateTimeImmutable($at, $timezone)
                : new \DateTimeImmutable('now', $timezone);
        } catch (\Throwable $e) {
            return null;
        }
        $date = $moment->format('Y-m-d');
        $time = $moment->format('H:i:s');
        $weekday = (int)$moment->format('N');
        try { $rows = Database::fetchAll("SELECT * FROM menu_price_rules WHERE restaurant_id = ? AND product_id = ? AND is_active = 1 AND (starts_on IS NULL OR starts_on <= ?) AND (ends_on IS NULL OR ends_on >= ?) ORDER BY id DESC", [$restaurantId, $productId, $date, $date]); } catch (\Throwable $e) { return null; }
        foreach ($rows as $rule) {
            $days = array_map('intval', explode(',', (string)($rule['days_of_week'] ?? '1,2,3,4,5,6,7')));
                        $start = substr((string)$rule['starts_at'], 0, 8); $end = substr((string)$rule['ends_at'], 0, 8);
            $overnight = $start >= $end;
            // For the after-midnight part of an overnight rule, the weekday is the day the rule started.
            $ruleWeekday = ($overnight && $time < $end) ? ($weekday === 1 ? 7 : $weekday - 1) : $weekday;
            if (!in_array($ruleWeekday, $days, true)) continue;
            $within = $overnight ? ($time >= $start || $time < $end) : ($time >= $start && $time < $end);
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
