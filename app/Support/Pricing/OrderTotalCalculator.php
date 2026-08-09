<?php

namespace App\Support\Pricing;

use InvalidArgumentException;
use OverflowException;

/**
 * Deterministic integer-only order totals.
 *
 * Money is integer centimes per kilogram and quantity is integer hundredths
 * of a kilogram. The one-time total rounds half up exactly once and never uses
 * floating-point math.
 */
final class OrderTotalCalculator
{
    /**
     * Parse a decimal quantity string with at most two fractional digits into
     * integer hundredths of a kilogram.
     */
    public static function parseQuantityKg(string $value): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Invalid quantity '{$value}'.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value), 2, '0');
        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
    }

    /**
     * Calculate the one-time half-up order total in centimes.
     */
    public static function totalMinor(int $unitPriceMinor, int $quantityHundredths): int
    {
        if ($unitPriceMinor <= 0) {
            throw new InvalidArgumentException('Unit price must be positive.');
        }

        if ($quantityHundredths <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        if ($unitPriceMinor > intdiv(PHP_INT_MAX, $quantityHundredths)) {
            throw new OverflowException('Order total overflow.');
        }

        $numerator = $unitPriceMinor * $quantityHundredths;
        $totalMinor = intdiv($numerator, 100);

        if ($numerator % 100 >= 50) {
            $totalMinor++;
        }

        return $totalMinor;
    }

    /**
     * Format integer centimes as a two-decimal number.
     */
    public static function formatMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format integer centimes per kilogram with the MAD currency label.
     */
    public static function formatPerKilogram(int $minor): string
    {
        return self::formatMinor($minor).' MAD/kg';
    }

    /**
     * Format an order total with the MAD currency label.
     */
    public static function formatTotal(int $minor): string
    {
        return self::formatMinor($minor).' MAD';
    }
}
