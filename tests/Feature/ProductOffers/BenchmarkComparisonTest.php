<?php

use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishedOfferWithComparisons(): array
{
    $operator = User::factory()->operator()->create();

    $offer = ProductOffer::factory()->published()->withStandardCosts()->create([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
    ]);

    $current = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
        'saving_minor' => 250,
        'saving_percentage_bps' => 3125,
        'published_at' => now()->subHour()->subMinute(),
    ]);

    $candidate = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 900,
        'observed_at' => now()->subMinutes(5),
    ]);

    return [$operator, $offer, $current, $candidate];
}

it('publishes a reviewed recorded comparison as an operator', function () {
    [$operator, $offer, $current, $candidate] = publishedOfferWithComparisons();

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertRedirectToRoute('operator.offers.edit', $offer)
        ->assertSessionHas('inertia.flash_data.toast');

    expect($candidate->refresh()->published_at)->not->toBeNull();
    expect($candidate->published_by_user_id)->toBe($operator->id);
    expect($candidate->saving_minor)->toBe(350);
    expect($candidate->saving_percentage_bps)->toBe(3889);
    expect($current->refresh()->superseded_at)->not->toBeNull();
    expect($current->isSuperseded())->toBeTrue();
});

it('forbids normal users from publishing a comparison', function () {
    $member = User::factory()->create();
    [, $offer, , $candidate] = publishedOfferWithComparisons();

    $this->actingAs($member)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertForbidden();

    expect($candidate->refresh()->published_at)->toBeNull();
});

it('returns a not-found for a comparison scoped to another offer', function () {
    [$operator, , , $candidate] = publishedOfferWithComparisons();
    $otherOffer = ProductOffer::factory()->published()->create();

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$otherOffer, $candidate]))
        ->assertNotFound();

    expect($candidate->refresh()->published_at)->toBeNull();
});

it('rejects a comparison observed more than twenty-four hours ago', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();
    $candidate = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'observed_at' => now()->subHours(24)->subSecond(),
    ]);

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertSessionHasErrors('benchmark_comparison_id');

    expect($candidate->refresh()->published_at)->toBeNull();
});

it('rejects a future comparison observation', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();
    $candidate = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'observed_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertSessionHasErrors('benchmark_comparison_id');

    expect($candidate->refresh()->published_at)->toBeNull();
});

it('leaves the published offer price unchanged when publishing a refresh', function () {
    [$operator, $offer, $current, $candidate] = publishedOfferWithComparisons();
    $publishedAt = $offer->published_at;

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]));

    $offer->refresh();

    expect($offer->published_at->equalTo($publishedAt))->toBeTrue();
    expect($offer->final_price_minor)->toBe(550);
    expect($offer->farmer_share_bps)->toBe(5091);
    expect($offer->costs()->count())->toBe(4);
});

it('shows recorded, published, and superseded comparisons to the operator', function () {
    [$operator, $offer, , $candidate] = publishedOfferWithComparisons();

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]));

    $this->actingAs($operator)
        ->get(route('operator.offers.edit', $offer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/manage')
            ->has('benchmarkComparisons', 2)
            ->where('benchmarkComparisons.0.isPublished', true)
            ->where('benchmarkComparisons.0.isSuperseded', false)
            ->where('benchmarkComparisons.1.isSuperseded', true));
});

it('publishes a refresh idempotently without creating extra rows', function () {
    [$operator, $offer, $current, $candidate] = publishedOfferWithComparisons();

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertRedirectToRoute('operator.offers.edit', $offer);

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertRedirectToRoute('operator.offers.edit', $offer);

    expect(BenchmarkComparison::query()->where('product_offer_id', $offer->id)->count())->toBe(2);
    expect($candidate->refresh()->published_at)->not->toBeNull();
    expect($candidate->saving_minor)->toBe(350);
    expect($current->refresh()->superseded_at)->not->toBeNull();
});

it('applies the operator throttle to comparison publication attempts', function () {
    RateLimiter::shouldReceive('limiter')
        ->with('operator-offers')
        ->andReturn(fn ($request) => Limit::perMinute(60)->by('test-operator'));

    RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);

    RateLimiter::shouldReceive('availableIn')->andReturn(60);

    [$operator, $offer, , $candidate] = publishedOfferWithComparisons();

    $this->actingAs($operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$offer, $candidate]))
        ->assertStatus(429);
});
