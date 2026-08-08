<?php

use App\Support\Pricing\OfferPriceCalculator;
use InvalidArgumentException;

it('parses decimal strings into centimes', function (string $input, int $expected) {
    expect(OfferPriceCalculator::parseMinor($input))->toBe($expected);
})->with([
    ['2.80', 280],
    ['0.30', 30],
    ['5', 500],
    ['5.5', 550],
    ['100.00', 10000],
    ['0', 0],
    ['0.00', 0],
    ['10.05', 1005],
]);

it('rejects malformed money values', function (string $input) {
    OfferPriceCalculator::parseMinor($input);
})->with([
    [''],
    ['abc'],
    ['-1'],
    ['1.234'],
    ['1,50'],
    ['1.2.3'],
    [' 2.80 '],
])->throws(InvalidArgumentException::class);

it('formats centimes as two-decimal numbers', function (int $minor, string $expected) {
    expect(OfferPriceCalculator::formatMinor($minor))->toBe($expected);
})->with([
    [550, '5.50'],
    [0, '0.00'],
    [-250, '-2.50'],
    [5, '0.05'],
    [10005, '100.05'],
]);

it('formats money with the MAD per kilogram label', function () {
    expect(OfferPriceCalculator::formatMoney(550))->toBe('5.50 MAD/kg');
    expect(OfferPriceCalculator::formatMoney(-250))->toBe('-2.50 MAD/kg');
});

it('formats percentage hundredths as signed percentages', function (int $bps, string $expected) {
    expect(OfferPriceCalculator::formatPercentage($bps))->toBe($expected);
})->with([
    [3125, '31.25%'],
    [5091, '50.91%'],
    [0, '0.00%'],
    [-1250, '-12.50%'],
]);

it('computes the final price as the exact integer sum', function () {
    expect(OfferPriceCalculator::finalPriceMinor(280, 170, 100))->toBe(550);
    expect(OfferPriceCalculator::finalPriceMinor(0, 0, 100))->toBe(100);
});

it('computes savings as benchmark minus final price', function () {
    expect(OfferPriceCalculator::savingMinor(800, 550))->toBe(250);
    expect(OfferPriceCalculator::savingMinor(550, 550))->toBe(0);
    expect(OfferPriceCalculator::savingMinor(400, 550))->toBe(-150);
});

it('rounds half-up with integer arithmetic only', function (int $numerator, int $denominator, int $expected) {
    expect(OfferPriceCalculator::roundHalfUp($numerator, $denominator))->toBe($expected);
})->with([
    [5, 2, 3],
    [1, 2, 1],
    [4, 2, 2],
    [3, 2, 2],
    [-5, 2, -3],
    [2500000, 800, 3125],
    [2800000, 550, 5091],
]);

it('rejects zero denominators', function () {
    OfferPriceCalculator::roundHalfUp(5, 0);
})->throws(InvalidArgumentException::class);

it('rejects an undefined farmer share for a zero final price', function () {
    OfferPriceCalculator::farmerShareBps(0, 0);
})->throws(InvalidArgumentException::class);

it('matches the illustrative tomato acceptance values', function () {
    $finalPrice = OfferPriceCalculator::finalPriceMinor(280, 30 + 20 + 30 + 90, 100);

    expect($finalPrice)->toBe(550);
    expect(OfferPriceCalculator::savingMinor(800, $finalPrice))->toBe(250);
    expect(OfferPriceCalculator::savingPercentageBps(250, 800))->toBe(3125);
    expect(OfferPriceCalculator::farmerShareBps(280, $finalPrice))->toBe(5091);
    expect(OfferPriceCalculator::formatMoney($finalPrice))->toBe('5.50 MAD/kg');
    expect(OfferPriceCalculator::formatMoney(OfferPriceCalculator::savingMinor(800, $finalPrice)))->toBe('2.50 MAD/kg');
    expect(OfferPriceCalculator::formatPercentage(OfferPriceCalculator::savingPercentageBps(250, 800)))->toBe('31.25%');
    expect(OfferPriceCalculator::formatPercentage(OfferPriceCalculator::farmerShareBps(280, $finalPrice)))->toBe('50.91%');
});
