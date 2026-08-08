<?php

namespace App\Support\Pricing;

use InvalidArgumentException;

/**
 * Deterministic integer-only pricing calculations.
 *
 * All monetary values are integer centimes per kilogram. All percentages are
 * signed integer hundredths of a percent (basis points scaled by 100).
 */
final class OfferPriceCalculator
{
    private const BASIS_POINTS_SCALE = 10000;

    /**
     * Parse a decimal string with at most two fractional digits into centimes.
     */
    public static function parseMinor(string $value): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Invalid money value '{$value}'.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value), 2, '0');
        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
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
    public static function formatMoney(int $minor): string
    {
        return self::formatMinor($minor).' MAD/kg';
    }

    /**
     * Round a signed quotient to the nearest integer, breaking ties toward
     * positive infinity, using only integer arithmetic.
     */
    public static function roundHalfUp(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        $sign = $numerator < 0 ? -1 : 1;
        $absolute = abs($numerator);
        $quotient = intdiv($absolute, $denominator);
        $remainder = $absolute % $denominator;

        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }

        return $sign * $quotient;
    }

    /**
     * Final price per kilogram: farmer payment plus all operating costs plus
     * platform margin.
     */
    public static function finalPriceMinor(
        int $farmerPaymentMinor,
        int $operatingCostMinor,
        int $platformMarginMinor,
    ): int {
        return $farmerPaymentMinor + $operatingCostMinor + $platformMarginMinor;
    }

    /**
     * Customer saving per kilogram: benchmark price minus final price. May be
     * zero or negative.
     */
    public static function savingMinor(int $benchmarkPriceMinor, int $finalPriceMinor): int
    {
        return $benchmarkPriceMinor - $finalPriceMinor;
    }

    /**
     * Farmer share as signed integer hundredths of a percent.
     */
    public static function farmerShareBps(int $farmerPaymentMinor, int $finalPriceMinor): int
    {
        return self::roundHalfUp(
            $farmerPaymentMinor * self::BASIS_POINTS_SCALE,
            $finalPriceMinor,
        );
    }

    /**
     * Saving percentage as signed integer hundredths of a percent.
     */
    public static function savingPercentageBps(int $savingMinor, int $benchmarkPriceMinor): int
    {
        return self::roundHalfUp(
            $savingMinor * self::BASIS_POINTS_SCALE,
            $benchmarkPriceMinor,
        );
    }

    /**
     * Format integer hundredths of a percent as a signed percentage string.
     */
    public static function formatPercentage(int $basisPoints): string
    {
        $sign = $basisPoints < 0 ? '-' : '';
        $absolute = abs($basisPoints);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT).'%';
    }
}
