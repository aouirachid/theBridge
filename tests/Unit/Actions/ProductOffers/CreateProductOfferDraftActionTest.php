<?php

use App\Actions\ProductOffers\CreateProductOfferDraftAction;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createDraftInput(array $overrides = []): array
{
    return array_replace_recursive([
        'crop' => 'Tomatoes',
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
    ], $overrides);
}

it('creates a draft with exact centimes and calculated values', function () {
    $user = User::factory()->operator()->create();

    $offer = app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput());

    expect($offer->status())->toBe('draft');
    expect($offer->published_at)->toBeNull();
    expect($offer->farmer_payment_minor)->toBe(280);
    expect($offer->platform_margin_minor)->toBe(100);
    expect($offer->final_price_minor)->toBe(550);
    expect($offer->farmer_share_bps)->toBe(5091);
    expect($offer->created_by_user_id)->toBe($user->id);
    expect($offer->public_id)->not->toBeNull();
    expect($offer->costs()->count())->toBe(4);
});

it('stores zero valued standard costs as explicit rows', function () {
    $user = User::factory()->operator()->create();

    $offer = app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
        'standard_costs' => [
            'collection' => '0.00',
            'quality_control' => '0.20',
            'hub_handling_storage' => '0.30',
            'delivery_allocation' => '0.90',
        ],
    ]));

    expect($offer->costs()->where('standard_code', 'collection')->first()->amount_minor)->toBe(0);
    expect($offer->final_price_minor)->toBe(520);
});

it('normalizes custom cost names', function () {
    $user = User::factory()->operator()->create();

    $offer = app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
        'custom_costs' => [
            ['name' => '  Cold   STORAGE ', 'amount_per_kg' => '0.25'],
        ],
    ]));

    $custom = $offer->costs()->whereNull('standard_code')->first();

    expect($custom->normalized_name)->toBe('cold storage');
    expect($custom->name)->toBe('Cold STORAGE');
    expect($custom->position)->toBe(100);
    expect($offer->final_price_minor)->toBe(575);
    expect($offer->farmer_share_bps)->toBe(4870);
});

it('rejects duplicate normalized custom names', function () {
    $user = User::factory()->operator()->create();

    expect(fn () => app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
        'custom_costs' => [
            ['name' => 'Cold storage', 'amount_per_kg' => '0.25'],
            ['name' => 'cold  STORAGE', 'amount_per_kg' => '0.10'],
        ],
    ])))->toThrow(ValidationException::class);
});

it('rejects custom names matching standard categories', function () {
    $user = User::factory()->operator()->create();

    expect(fn () => app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
        'custom_costs' => [
            ['name' => 'Collection', 'amount_per_kg' => '0.25'],
        ],
    ])))->toThrow(ValidationException::class);
});

it('rejects more than ten custom components', function () {
    $user = User::factory()->operator()->create();

    $many = collect(range(1, 11))->map(fn ($i) => ['name' => "Extra cost $i", 'amount_per_kg' => '0.10'])->all();

    expect(fn () => app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
        'custom_costs' => $many,
    ])))->toThrow(ValidationException::class);
});

it('persists nothing when validation fails', function () {
    $user = User::factory()->operator()->create();

    try {
        app(CreateProductOfferDraftAction::class)->execute($user, createDraftInput([
            'custom_costs' => [
                ['name' => 'Cold storage', 'amount_per_kg' => '0.25'],
                ['name' => 'COLD STORAGE', 'amount_per_kg' => '0.10'],
            ],
        ]));
    } catch (ValidationException) {
    }

    expect(ProductOffer::query()->count())->toBe(0);
    expect(OfferCostComponent::query()->count())->toBe(0);
});
