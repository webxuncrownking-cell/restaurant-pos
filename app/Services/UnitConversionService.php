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

        if (!is_finite($quantity) || $quantity < 0) {
            throw new InvalidArgumentException('Quantity must be a finite, non-negative number.');
        }
        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Both source and target units are required.');
        }
        if ($from === $to) {
            return $quantity;
        }

        // With an ingredient context, normalize BOTH units through that ingredient's
        // base unit. This correctly handles configured package factors such as tray_30,
        // bottle_1L, and sack_25kg, including package-to-kg conversions.
        if ($ingredientId !== null) {
            $ingredient = Database::fetchOne(
                "SELECT base_unit FROM ingredients WHERE id = ? AND restaurant_id = ? AND is_active = 1",
                [$ingredientId, $restaurantId]
            );
            if (!$ingredient) {
                throw new InvalidArgumentException('The selected ingredient is not available for this restaurant.');
            }
            $baseUnit = (string)$ingredient['base_unit'];

            $fromCustom = Database::fetchOne(
                "SELECT multiplier_to_base FROM ingredient_unit_conversions
                 WHERE restaurant_id = ? AND ingredient_id = ? AND LOWER(unit_name) = ?",
                [$restaurantId, $ingredientId, $from]
            );
            $toCustom = Database::fetchOne(
                "SELECT multiplier_to_base FROM ingredient_unit_conversions
                 WHERE restaurant_id = ? AND ingredient_id = ? AND LOWER(unit_name) = ?",
                [$restaurantId, $ingredientId, $to]
            );

            if ($fromCustom !== null) {
                $fromFactor = (float)$fromCustom['multiplier_to_base'];
            } elseif (isset(self::STANDARD_CONVERSIONS[$from])) {
                $fromData = self::STANDARD_CONVERSIONS[$from];
                if ($fromData['base'] !== $baseUnit) {
                    throw new InvalidArgumentException("Unit '{$fromUnit}' is incompatible with ingredient base unit '{$baseUnit}'.");
                }
                $fromFactor = (float)$fromData['factor'];
            } elseif ($from === $baseUnit) {
                $fromFactor = 1.0;
            } else {
                throw new InvalidArgumentException("No conversion rule found from '{$fromUnit}' for this ingredient.");
            }

            if ($fromFactor <= 0 || !is_finite($fromFactor)) {
                throw new InvalidArgumentException("Invalid conversion factor for unit '{$fromUnit}'.");
            }
            $baseQuantity = $quantity * $fromFactor;

            if ($to === $baseUnit) {
                return $baseQuantity;
            }
            if ($toCustom !== null) {
                $toFactor = (float)$toCustom['multiplier_to_base'];
                if ($toFactor <= 0 || !is_finite($toFactor)) {
                    throw new InvalidArgumentException("Invalid conversion factor for unit '{$toUnit}'.");
                }
                return $baseQuantity / $toFactor;
            }
            if (isset(self::STANDARD_CONVERSIONS[$to])) {
                $toData = self::STANDARD_CONVERSIONS[$to];
                if ($toData['base'] !== $baseUnit) {
                    throw new InvalidArgumentException("Unit '{$toUnit}' is incompatible with ingredient base unit '{$baseUnit}'.");
                }
                return $baseQuantity / (float)$toData['factor'];
            }
            throw new InvalidArgumentException("No conversion rule found to '{$toUnit}' for this ingredient.");
        }

        // Without an ingredient, only standard dimensional units are safe to convert.
        if (isset(self::STANDARD_CONVERSIONS[$from], self::STANDARD_CONVERSIONS[$to])) {
            $fromData = self::STANDARD_CONVERSIONS[$from];
            $toData = self::STANDARD_CONVERSIONS[$to];
            if ($fromData['base'] !== $toData['base']) {
                throw new InvalidArgumentException(
                    "Cannot convert between incompatible dimensions: '{$from}' ({$fromData['base']}) and '{$to}' ({$toData['base']})."
                );
            }
            return ($quantity * (float)$fromData['factor']) / (float)$toData['factor'];
        }

        throw new InvalidArgumentException(
            "No conversion rule found from '{$fromUnit}' to '{$toUnit}' without an ingredient context."
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
        if ($restaurantId <= 0 || $conversionId <= 0 || !is_finite($packageCount) || $packageCount <= 0) {
            throw new InvalidArgumentException('Restaurant, packaging unit, and package count must be valid positive values.');
        }

        $conv = Database::fetchOne(
            "SELECT c.*, i.base_unit, i.name as ingredient_name, i.cost_per_base_unit
             FROM ingredient_unit_conversions c
             JOIN ingredients i ON c.ingredient_id = i.id AND c.restaurant_id = i.restaurant_id
             WHERE c.id = ? AND c.restaurant_id = ? AND i.is_active = 1",
            [$conversionId, $restaurantId]
        );

        if (!$conv) {
            throw new InvalidArgumentException("Packaging conversion #{$conversionId} not found for this restaurant.");
        }

        $multiplier = (float)$conv['multiplier_to_base'];
        if (!is_finite($multiplier) || $multiplier <= 0) {
            throw new InvalidArgumentException('The selected packaging conversion has an invalid multiplier.');
        }
        $totalBaseQuantity = $packageCount * $multiplier;
        if (!is_finite($totalBaseQuantity) || $totalBaseQuantity <= 0) {
            throw new InvalidArgumentException('The normalized stock quantity is invalid.');
        }
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
