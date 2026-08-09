<?php

use App\Actions\ProductOffers\CreateReplacementOfferDraftAction;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function replacementSourceOffer(array $overrides = []): ProductOffer
{
    $source = ProductOffer::factory()
        ->published()
        ->withStandardCosts()
        ->create(array_merge([
            'farmer_payment_minor' => 280,
            'platform_margin_minor' => 100,
        ], $overrides));

    $source->costs()->create([
        'standard_code' => null,
        'name' => 'Packaging',
        'normalized_name' => OfferCostComponent::normalizeName('Packaging'),
        'amount_minor' => 25,
        'position' => 100,
    ]);

    $source->forceFill([
        'final_price_minor' => 575,
        'farmer_share_bps' => 4870,
    ])->save();

    return $source;
}

it('clones immutable offer inputs into one editable draft', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();

    $draft = app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    $draft->refresh();

    expect($draft->id)->not->toBe($source->id);
    expect($draft->isDraft())->toBeTrue();
    expect($draft->published_at)->toBeNull();
    expect($draft->superseded_at)->toBeNull();
    expect($draft->withdrawn_at)->toBeNull();
    expect($draft->status())->toBe('draft');

    expect($draft->crop)->toBe($source->crop);
    expect($draft->origin)->toBe($source->origin);
    expect($draft->available_quantity_kg)->toBe($source->available_quantity_kg);
    expect($draft->availability_starts_at->equalTo($source->availability_starts_at))->toBeTrue();
    expect($draft->availability_ends_at->equalTo($source->availability_ends_at))->toBeTrue();
    expect($draft->farmer_payment_minor)->toBe($source->farmer_payment_minor);
    expect($draft->platform_margin_minor)->toBe($source->platform_margin_minor);
    expect($draft->final_price_minor)->toBe(575);
    expect($draft->farmer_share_bps)->toBe(4870);
});

it('clones the full cost collection with exact normalized names and positions', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();

    $draft = app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    $draftCosts = $draft->costs()->get();

    expect($draftCosts)->toHaveCount(5);

    foreach ($draftCosts as $cost) {
        $sourceCost = OfferCostComponent::query()
            ->where('product_offer_id', $source->id)
            ->where('standard_code', $cost->standard_code)
            ->where('normalized_name', $cost->normalized_name)
            ->where('position', $cost->position)
            ->first();

        expect($sourceCost)->not->toBeNull();
        expect($cost->name)->toBe($sourceCost->name);
        expect($cost->amount_minor)->toBe($sourceCost->amount_minor);
    }
});

it('links the replacement draft to its predecessor exactly once', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();

    $draft = app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    expect($draft->replaces_product_offer_id)->toBe($source->id);
    expect($source->replacement()->count())->toBe(1);
    expect($source->replacement()->first()->id)->toBe($draft->id);
});

it('assigns the private actor as the new draft creator', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();

    $draft = app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    expect($draft->created_by_user_id)->toBe($operator->id);
});

it('rejects cloning a draft source offer', function () {
    $operator = User::factory()->operator()->create();
    $source = ProductOffer::factory()->create();

    expect(fn () => app(CreateReplacementOfferDraftAction::class)->execute($operator, $source))
        ->toThrow(RuntimeException::class);
});

it('rejects cloning a superseded source offer', function () {
    $operator = User::factory()->operator()->create();
    $source = ProductOffer::factory()->create([
        'published_at' => now()->subDays(5),
        'superseded_at' => now()->subDay(),
        'withdrawn_at' => null,
    ]);

    expect(fn () => app(CreateReplacementOfferDraftAction::class)->execute($operator, $source))
        ->toThrow(RuntimeException::class);
});

it('rejects cloning a withdrawn source offer', function () {
    $operator = User::factory()->operator()->create();
    $source = ProductOffer::factory()->withdrawn()->create();

    expect(fn () => app(CreateReplacementOfferDraftAction::class)->execute($operator, $source))
        ->toThrow(RuntimeException::class);
});

it('prevents a duplicate replacement for the same offer', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();

    app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    expect(fn () => app(CreateReplacementOfferDraftAction::class)->execute($operator, $source))
        ->toThrow(RuntimeException::class);

    expect($source->replacement()->count())->toBe(1);
});

it('keeps the published source offer untouched when cloning', function () {
    $operator = User::factory()->operator()->create();
    $source = replacementSourceOffer();
    $publishedAt = $source->published_at;

    app(CreateReplacementOfferDraftAction::class)->execute($operator, $source);

    $source->refresh();

    expect($source->published_at->equalTo($publishedAt))->toBeTrue();
    expect($source->superseded_at)->toBeNull();
    expect($source->withdrawn_at)->toBeNull();
    expect($source->final_price_minor)->toBe(575);
    expect($source->costs()->count())->toBe(5);
});
