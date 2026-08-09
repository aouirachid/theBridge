<?php

use App\Actions\ProductOffers\UpdateProductOfferDraftAction;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates an unpublished draft and recalculates the snapshot', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create([
        'crop' => 'Tomatoes',
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
    ]);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);

    $updated = app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
        'crop' => 'Carrots',
        'origin' => 'Guelmim-Oued Noun',
        'available_quantity_kg' => '250.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_per_kg' => '3.20',
        'platform_margin_per_kg' => '0.80',
        'standard_costs' => [
            'collection' => '0.40',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
        ],
        'custom_costs' => [
            ['name' => 'Cold storage', 'amount_per_kg' => '0.25'],
        ],
        'delivery_slots' => [
            ['starts_at' => now()->addDays(2)->startOfDay()->addHours(2), 'ends_at' => now()->addDays(2)->startOfDay()->addHours(4)],
        ],
    ]);

    $updated->refresh();

    expect($updated->crop)->toBe('Carrots');
    expect($updated->available_quantity_kg)->toBe('250.00');
    expect($updated->farmer_payment_minor)->toBe(320);
    expect($updated->platform_margin_minor)->toBe(80);
    expect($updated->final_price_minor)->toBe(605);
    expect($updated->farmer_share_bps)->toBe(5289);
    expect($updated->status())->toBe('draft');
    expect($updated->costs()->count())->toBe(5);
    expect($updated->deliverySlots()->count())->toBe(1);
});

it('replaces the complete delivery slot collection on update', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create([
        'crop' => 'Tomatoes',
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
    ]);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);
    $offer->deliverySlots()->createMany([
        ['starts_at' => now()->addDays(2)->startOfDay()->addHours(2), 'ends_at' => now()->addDays(2)->startOfDay()->addHours(4)],
        ['starts_at' => now()->addDays(3)->startOfDay()->addHours(2), 'ends_at' => now()->addDays(3)->startOfDay()->addHours(4)],
    ]);

    app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
        'crop' => 'Carrots',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_per_kg' => '2.80',
        'platform_margin_per_kg' => '1.00',
        'standard_costs' => [
            'collection' => '0.30',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
        ],
        'custom_costs' => [],
        'delivery_slots' => [
            ['starts_at' => now()->addDays(4)->startOfDay()->addHours(2), 'ends_at' => now()->addDays(4)->startOfDay()->addHours(4)],
        ],
    ]);

    $slots = $offer->deliverySlots()->get();

    expect($slots)->toHaveCount(1);
    expect($slots->first()->starts_at->toDateString())->toBe(now()->addDays(4)->startOfDay()->addHours(2)->toDateString());
});

it('rejects updating a published offer', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->create();

    expect(fn () => app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
        'crop' => 'Carrots',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_per_kg' => '2.80',
        'platform_margin_per_kg' => '1.00',
        'standard_costs' => [
            'collection' => '0.30',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
        ],
        'custom_costs' => [],
    ]))->toThrow(RuntimeException::class);
});

it('rejects updating a withdrawn offer', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->withdrawn()->create();

    expect(fn () => app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
        'crop' => 'Carrots',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_per_kg' => '2.80',
        'platform_margin_per_kg' => '1.00',
        'standard_costs' => [
            'collection' => '0.30',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
        ],
        'custom_costs' => [],
    ]))->toThrow(RuntimeException::class);
});

it('rejects unknown standard cost codes', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
        'crop' => 'Carrots',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay(),
        'availability_ends_at' => now()->addDays(8)->startOfDay(),
        'farmer_payment_per_kg' => '2.80',
        'platform_margin_per_kg' => '1.00',
        'standard_costs' => [
            'collection' => '0.30',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
            'mystery' => '0.50',
        ],
        'custom_costs' => [],
    ]))->toThrow(ValidationException::class);
});

it('persists nothing when validation fails', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create(['crop' => 'Tomatoes']);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);

    try {
        app(UpdateProductOfferDraftAction::class)->execute($user, $offer, [
            'crop' => 'Carrots',
            'origin' => 'Souss-Massa',
            'available_quantity_kg' => '100.00',
            'availability_starts_at' => now()->addDay()->startOfDay(),
            'availability_ends_at' => now()->addDays(8)->startOfDay(),
            'farmer_payment_per_kg' => '2.80',
            'platform_margin_per_kg' => '1.00',
            'standard_costs' => [
                'collection' => '0.30',
                'quality_control' => '0.20',
                'hub_handling_storage' => '0.30',
                'delivery_allocation' => '0.90',
            ],
            'custom_costs' => [
                ['name' => 'Cold storage', 'amount_per_kg' => '0.25'],
                ['name' => 'COLD STORAGE', 'amount_per_kg' => '0.10'],
            ],
        ]);
    } catch (ValidationException) {
    }

    $offer->refresh();

    expect($offer->crop)->toBe('Tomatoes');
    expect($offer->costs()->count())->toBe(4);
});
