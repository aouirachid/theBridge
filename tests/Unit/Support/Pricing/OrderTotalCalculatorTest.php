<?php

use App\Support\Pricing\OrderTotalCalculator;
use InvalidArgumentException;
use OverflowException;

test('it parses quantity kilograms into integer hundredths', function (string $input, int $expected) {
    expect(OrderTotalCalculator::parseQuantityKg($input))->toBe($expected);
})->with([
    ['5', 500],
    ['5.2', 520],
    ['5.25', 525],
    ['0', 0],
    ['0.01', 1],
    ['1000', 100000],
]);

test('it rejects malformed quantity kilograms', function (string $input) {
    expect(fn () => OrderTotalCalculator::parseQuantityKg($input))
        ->toThrow(InvalidArgumentException::class);
})->with([
    '',
    'abc',
    '5.255',
    '5.',
    '.5',
    '-1',
    '1e2',
    '5,2',
]);

test('it calculates the one-time half-up total in centimes', function (int $unitPriceMinor, int $quantityHundredths, int $expected) {
    expect(OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths))->toBe($expected);
})->with([
    [550, 500, 2750],
    [550, 4000, 22000],
    [550, 525, 2888],
    [1, 125, 1],
    [1, 149, 1],
    [1, 150, 2],
    [100, 100, 100],
]);

test('it rejects non-positive total inputs', function (int $unitPriceMinor, int $quantityHundredths) {
    expect(fn () => OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [0, 500],
    [550, 0],
    [-1, 500],
    [550, -500],
]);

test('it rejects multiplication overflow', function (int $unitPriceMinor, int $quantityHundredths) {
    expect(fn () => OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths))
        ->toThrow(OverflowException::class);
})->with([
    [PHP_INT_MAX, 2],
    [2, PHP_INT_MAX],
    [PHP_INT_MAX, PHP_INT_MAX],
]);

test('it formats integer centimes as a two-decimal string', function (int $minor, string $expected) {
    expect(OrderTotalCalculator::formatMinor($minor))->toBe($expected);
})->with([
    [2750, '27.50'],
    [2888, '28.88'],
    [0, '0.00'],
    [5, '0.05'],
    [22000, '220.00'],
]);

test('it formats per-kilogram and total money labels', function () {
    expect(OrderTotalCalculator::formatPerKilogram(550))->toBe('5.50 MAD/kg');
    expect(OrderTotalCalculator::formatPerKilogram(100))->toBe('1.00 MAD/kg');
    expect(OrderTotalCalculator::formatTotal(2750))->toBe('27.50 MAD');
    expect(OrderTotalCalculator::formatTotal(22000))->toBe('220.00 MAD');
    expect(OrderTotalCalculator::formatTotal(2888))->toBe('28.88 MAD');
});
