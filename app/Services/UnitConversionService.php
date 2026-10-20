<?php
/**
 * Restaurant POS & Management SaaS
 * Unit Conversion & Packaging Normalization Engine
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;

class UnitConversionService
{
    /**
     * Standard built-in metric conversion ratios relative to base units:
     * - Weight base unit: 'g' (gram)
     * - Volume base unit: 'ml' (milliliter)
     * - Count base unit: 'pcs' (pieces)
     */
    private const STANDARD_CONVERSIONS = [
        // Mass/Weight -> Base: g
        'kg'  => ['base' => 'g', 'factor' => 1000.0],
        'g'   => ['base' => 'g', 'factor' => 1.0],
        'mg'  => ['base' => 'g', 'factor' => 0.001],
        'lb'  => ['base' => 'g', 'factor' => 453.59237],
        'oz'  => ['base' => 'g', 'factor' => 28.3495],

        // Volume -> Base: ml
        'l'   => ['base' => 'ml', 'factor' => 1000.0],
        'ml'  => ['base' => 'ml', 'factor' => 1.0],
        'cl'  => ['base' => 'ml', 'factor' => 10.0],
        'gal' => ['base' => 'ml', 'factor' => 3785.41],
        'cup' => ['base' => 'ml', 'factor' => 240.0],
        'tbsp'=> ['base' => 'ml', 'factor' => 15.0],
        'tsp' => ['base' => 'ml', 'factor' => 5.0],

        // Count -> Base: pcs
        'pcs' => ['base' => 'pcs', 'factor' => 1.0],
        'pc'  => ['base' => 'pcs', 'factor' => 1.0],
        'dz'  => ['base' => 'pcs', 'factor' => 12.0],
        'tray'=> ['base' => 'pcs', 'factor' => 30.0],
        'box' => ['base' => 'pcs', 'factor' => 1.0],
        'pack'=> ['base' => 'pcs', 'factor' => 1.0],
    ];

    /**
     * Convert an amount from one unit to another for a specific restaurant tenant.
     */
    public static function convert(
        int $restaurantId,
        float $quantity,
        string $fromUnit,
        string $toUnit,
        ?int $ingredientId = null
    ): float {
        $from = strtolower(trim($fromUnit));
        $to = strtolower(trim($toUnit));

        if ($from === $to) {
            return $quantity;
        }

        // 1. Check ingredient-specific packaging conversions (e.g. 1 tray_30 = 30 pcs)
        if ($ingredientId !== null) {
            $custom = Database::fetchOne(
                "SELECT multiplier_to_base FROM ingredient_unit_conversions 
                 WHERE restaurant_id = ? AND ingredient_id = ? AND LOWER(unit_name) = ?",
                [$restaurantId, $ingredientId, $from]
            );

            if ($custom && (float)$custom['multiplier_to_base'] > 0) {
                return $quantity * (float)$custom['multiplier_to_base'];
            }
        }

        // 2. Fallback to standard metric conversions
        if (isset(self::STANDARD_CONVERSIONS[$from], self::STANDARD_CONVERSIONS[$to])) {
            $fromData = self::STANDARD_CONVERSIONS[$from];
            $toData = self::STANDARD_CONVERSIONS[$to];

            if ($fromData['base'] !== $toData['base']) {
                throw new InvalidArgumentException(
                    "Cannot convert between incompatible dimensions: '{$from}' ({$fromData['base']}) and '{$to}' ({$toData['base']})."
                );
            }

            // Convert to common base unit, then to target unit
            $baseQty = $quantity * $fromData['factor'];
            return $baseQty / $toData['factor'];
        }

        throw new InvalidArgumentException(
            "No conversion rule found from '{$fromUnit}' to '{$toUnit}' for restaurant ID {$restaurantId}."
        );
    }

    /**
     * Convert an ingredient packaging conversion record into base ingredient units.
     * E.g. 4 trays of "Eggs (30 pcs/tray)" => 120 pcs
     */
    public static function convertPackageToBase(
        int $restaurantId,
        int $conversionId,
        float $packageCount
    ): array {
        $conv = Database::fetchOne(
            "SELECT c.*, i.base_unit, i.name as ingredient_name, i.cost_per_base_unit 
             FROM ingredient_unit_conversions c
             JOIN ingredients i ON c.ingredient_id = i.id AND c.restaurant_id = i.restaurant_id
             WHERE c.id = ? AND c.restaurant_id = ?",
            [$conversionId, $restaurantId]
        );

        if (!$conv) {
            throw new InvalidArgumentException("Packaging conversion #{$conversionId} not found for this restaurant.");
        }

        $multiplier = (float)$conv['multiplier_to_base'];
        $totalBaseQuantity = $packageCount * $multiplier;
        $costPerBase = (float)$conv['cost_per_base_unit'];

        return [
            'ingredient_id'       => (int)$conv['ingredient_id'],
            'ingredient_name'     => $conv['ingredient_name'],
            'package_name'        => $conv['unit_name'],
            'package_count'       => $packageCount,
            'multiplier_to_base'  => $multiplier,
            'total_base_quantity' => $totalBaseQuantity,
            'base_unit'           => $conv['base_unit'],
            'cost_per_base_unit'  => $costPerBase
        ];
    }
}
