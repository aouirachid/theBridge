<?php

use App\Actions\ProductOffers\ShowProductOfferDraftAction;
use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function draftOfferWithEverything(): array
{
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create([
        'crop' => 'Tomatoes',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
        'final_price_minor' => 550,
        'farmer_share_bps' => 5091,
    ]);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
        OfferCostComponent::factory()->make([
            'name' => 'Cold storage',
            'normalized_name' => 'cold storage',
            'amount_minor' => 25,
            'position' => 100,
        ]),
    ]);

    return [$user, $offer];
}

it('returns the exact stored inputs back as strings', function () {
    [$user, $offer] = draftOfferWithEverything();

    $editor = app(ShowProductOfferDraftAction::class)->execute($user, $offer);

    expect($editor['offer'])->toMatchArray([
        'crop' => 'Tomatoes',
        'origin' => 'Souss-Massa',
        'availableQuantity' => '100.00',
        'farmerPayment' => '2.80',
        'platformMargin' => '1.00',
        'finalPrice' => '5.50',
        'status' => 'draft',
    ]);
});

it('returns costs ordered with standard first then custom', function () {
    [$user, $offer] = draftOfferWithEverything();

    $editor = app(ShowProductOfferDraftAction::class)->execute($user, $offer);

    expect(collect($editor['costs']['standard'])->pluck('code')->all())
        ->toBe(['collection', 'quality_control', 'hub_handling_storage', 'delivery_allocation']);

    expect(collect($editor['costs']['standard'])->pluck('position')->all())
        ->toBe([10, 20, 30, 40]);

    expect($editor['costs']['standard'][0])->toMatchArray([
        'code' => 'collection',
        'label' => 'Collection',
        'amount' => '0.30',
    ]);

    expect($editor['costs']['custom'][0])->toMatchArray([
        'name' => 'Cold storage',
        'normalized_name' => 'cold storage',
        'amount' => '0.25',
    ]);
});

it('returns a calculated preview for editing', function () {
    [$user, $offer] = draftOfferWithEverything();

    $editor = app(ShowProductOfferDraftAction::class)->execute($user, $offer);

    expect($editor['preview'])->toMatchArray([
        'farmerPayment' => '2.80',
        'operatingCost' => '1.95',
        'platformMargin' => '1.00',
        'finalPrice' => '5.75',
        'farmerShare' => '48.70%',
    ]);
});

it('returns at most the newest thirty benchmark rows', function () {
    [$user, $offer] = draftOfferWithEverything();

    BenchmarkComparison::factory()->count(35)->sequence(
        fn ($seq) => ['observed_at' => now()->subMinutes(35 - $seq->index)],
    )->create(['product_offer_id' => $offer->id]);

    $editor = app(ShowProductOfferDraftAction::class)->execute($user, $offer);

    expect($editor['benchmarks']['newest30'])->toHaveCount(30);

    $first = $editor['benchmarks']['newest30'][0];
    $last = $editor['benchmarks']['newest30'][29];

    expect($first['observedAt'])->toBeGreaterThanOrEqual($last['observedAt']);
});

it('returns read-only capabilities for a published offer', function () {
    [$user] = draftOfferWithEverything();
    $offer = ProductOffer::factory()->published()->create();

    $editor = app(ShowProductOfferDraftAction::class)->execute($user, $offer);

    expect($editor['capabilities'])->toMatchArray([
        'canUpdate' => false,
        'canPublish' => false,
        'canReplace' => true,
        'canWithdraw' => true,
        'canRecordBenchmark' => true,
    ]);

    expect($editor['offer']['status'])->toBe('published');
});
