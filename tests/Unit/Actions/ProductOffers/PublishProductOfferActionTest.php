<?php

use App\Actions\ProductOffers\PublishProductOfferAction;
use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function publishableOffer(User $user, array $offerOverrides = []): array
{
    $offer = ProductOffer::factory()->create(array_merge([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
        'final_price_minor' => 999,
        'farmer_share_bps' => 1,
    ], $offerOverrides));
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);

    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
    ]);

    $offer->deliverySlots()->create([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
    ]);

    return [$offer, $benchmark];
}

it('publishes the offer with a consistent snapshot', function () {
    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);

    $published = app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark);

    $published->refresh();

    expect($published->published_at->isPast())->toBeTrue();
    expect($published->final_price_minor)->toBe(550);
    expect($published->farmer_share_bps)->toBe(5091);
    expect($published->status())->toBe('published');
});

it('publishes the selected recorded benchmark with savings', function () {
    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);

    app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark);

    $benchmark->refresh();

    expect($benchmark->published_at->isPast())->toBeTrue();
    expect($benchmark->published_by_user_id)->toBe($user->id);
    expect($benchmark->saving_minor)->toBe(250);
    expect($benchmark->saving_percentage_bps)->toBe(3125);
    expect($benchmark->isPublished())->toBeTrue();
});

it('rejects a future benchmark observation', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);
    $benchmark->forceFill(['observed_at' => now()->addMinutes(5)])->save();

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark))
        ->toThrow(RuntimeException::class);
});

it('rejects a stale benchmark observation older than twenty-four hours', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);
    $benchmark->forceFill(['observed_at' => now()->subHours(24)->subSecond()])->save();

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark))
        ->toThrow(RuntimeException::class);
});

it('accepts a benchmark observed exactly twenty-four hours ago', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);
    $benchmark->forceFill(['observed_at' => now()->subHours(24)])->save();

    $published = app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark);

    expect($published->status())->toBe('published');
});

it('rejects a draft missing standard cost components', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create(['farmer_payment_minor' => 280, 'platform_margin_minor' => 100]);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
    ]);
    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
    ]);

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark))
        ->toThrow(RuntimeException::class);
});

it('rejects publishing a draft without a future delivery slot', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
        'final_price_minor' => 999,
        'farmer_share_bps' => 1,
    ]);
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);
    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
    ]);

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark))
        ->toThrow(RuntimeException::class, 'At least one future delivery slot is required for publication.');
});

it('rejects a benchmark that belongs to another offer', function () {
    $user = User::factory()->operator()->create();
    $otherOffer = ProductOffer::factory()->create();
    [$offer] = publishableOffer($user);
    $foreignBenchmark = BenchmarkComparison::factory()->create(['product_offer_id' => $otherOffer->id]);

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $foreignBenchmark))
        ->toThrow(RuntimeException::class);
});

it('rejects publishing an already published offer', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->create();
    $benchmark = BenchmarkComparison::factory()->create(['product_offer_id' => $offer->id]);

    expect(fn () => app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark))
        ->toThrow(RuntimeException::class);
});

it('supersedes the predecessor when publishing a replacement', function () {
    $user = User::factory()->operator()->create();
    $predecessor = ProductOffer::factory()->published()->create();
    [$offer, $benchmark] = publishableOffer($user, ['replaces_product_offer_id' => $predecessor->id]);

    app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark);

    expect($predecessor->refresh()->superseded_at)->not->toBeNull();
    expect($predecessor->status())->toBe('superseded');
    expect($predecessor->final_price_minor)->toBe(550);
});

it('stays atomic when publishing fails', function () {
    $user = User::factory()->operator()->create();
    [$offer, $benchmark] = publishableOffer($user);
    $benchmark->forceFill(['observed_at' => now()->subDays(3)])->save();

    try {
        app(PublishProductOfferAction::class)->execute($user, $offer, $benchmark);
    } catch (RuntimeException) {
    }

    $offer->refresh();
    $benchmark->refresh();

    expect($offer->published_at)->toBeNull();
    expect($offer->final_price_minor)->toBe(999);
    expect($offer->farmer_share_bps)->toBe(1);
    expect($benchmark->published_at)->toBeNull();
    expect($benchmark->saving_minor)->toBeNull();
});
