<?php

use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishedPublicOffer(array $offerOverrides = [], array $comparisonOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create($offerOverrides);

    BenchmarkComparison::factory()->create(array_merge([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'market_name' => 'Casablanca traditional market',
        'source_type' => 'field_observation',
        'source_reference' => 'Phase 1 demo observation',
        'observed_at' => now()->subHour(),
        'is_demo' => true,
        'saving_minor' => 250,
        'saving_percentage_bps' => 3125,
        'published_at' => now()->subHour()->subMinute(),
    ], $comparisonOverrides));

    return $offer;
}

it('binds the offer by random public id and exposes the exact allowlisted props', function () {
    $offer = publishedPublicOffer();

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offers/show')
            ->where('offer.publicId', (string) $offer->public_id)
            ->where('offer.crop', 'Tomatoes')
            ->where('offer.origin', 'Souss-Massa')
            ->where('offer.availableQuantityKg', '100.00')
            ->where('offer.farmerPayment', ['minor' => 280, 'formatted' => '2.80 MAD/kg'])
            ->has('offer.costs', 4)
            ->where('offer.costs.0.code', 'collection')
            ->where('offer.costs.0.name', 'Collection')
            ->where('offer.costs.0.amount', ['minor' => 30, 'formatted' => '0.30 MAD/kg'])
            ->where('offer.platformMargin', ['minor' => 100, 'formatted' => '1.00 MAD/kg'])
            ->where('offer.finalPrice', ['minor' => 550, 'formatted' => '5.50 MAD/kg'])
            ->where('offer.farmerSharePercentage', '50.91%')
            ->where('offer.isSuperseded', false)
            ->where('offer.replacesPublicId', null)
            ->where('offer.replacementPublicId', null)
            ->where('freshComparisonUnavailable', false)
            ->where('currentComparison.marketName', 'Casablanca traditional market')
            ->where('currentComparison.sourceType', 'field_observation')
            ->where('currentComparison.sourceReference', 'Phase 1 demo observation')
            ->where('currentComparison.isDemo', true)
            ->where('currentComparison.benchmarkPrice', ['minor' => 800, 'formatted' => '8.00 MAD/kg'])
            ->where('currentComparison.saving', ['minor' => 250, 'formatted' => '2.50 MAD/kg'])
            ->where('currentComparison.savingPercentage', '31.25%')
            ->where('currentComparison.isSuperseded', false)
            ->where('currentComparison.supersededAt', null)
            ->where('comparisonHistory', []));
});

it('never exposes actor, internal, or private fields anywhere', function () {
    $offer = publishedPublicOffer();

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('offer.id')
            ->missing('offer.created_by_user_id')
            ->missing('offer.recorded_by_user_id')
            ->missing('offer.published_by_user_id')
            ->missing('offer.creator')
            ->missing('offer.recorder')
            ->missing('offer.publisher')
            ->missing('offer.user')
            ->missing('offer.email')
            ->missing('offer.phone')
            ->missing('offer.exact_address')
            ->missing('offer.normalized_name')
            ->missing('currentComparison.id')
            ->missing('currentComparison.created_by_user_id')
            ->missing('currentComparison.recorded_by_user_id')
            ->missing('currentComparison.published_by_user_id')
            ->missing('currentComparison.creator')
            ->missing('currentComparison.recorder')
            ->missing('currentComparison.publisher')
            ->missing('currentComparison.user')
            ->missing('currentComparison.email')
            ->missing('currentComparison.phone')
            ->missing('currentComparison.exact_address')
            ->missing('currentComparison.normalized_name')
            ->missing('comparisonHistory.0.id')
            ->missing('comparisonHistory.0.created_by_user_id')
            ->missing('comparisonHistory.0.recorded_by_user_id')
            ->missing('comparisonHistory.0.published_by_user_id')
            ->missing('comparisonHistory.0.email')
            ->missing('comparisonHistory.0.exact_address'));

    $response = $this->get(route('offers.show', $offer->public_id))->assertOk();

    $props = $response->viewData('page')['props'];

    $forbidden = [
        'id',
        'created_by_user_id',
        'recorded_by_user_id',
        'published_by_user_id',
        'creator',
        'recorder',
        'publisher',
        'user',
        'email',
        'phone',
        'exact_address',
        'normalized_name',
    ];

    expect(array_keys($props))->toContain(
        'offer',
        'currentComparison',
        'freshComparisonUnavailable',
        'comparisonHistory',
    );

    foreach (['offer', 'currentComparison', 'freshComparisonUnavailable', 'comparisonHistory'] as $prop) {
        if (is_array($props[$prop])) {
            array_walk_recursive($props[$prop], function ($value, $key) use ($forbidden): void {
                expect($forbidden)->not->toContain($key);
            });
        }
    }
});

it('labels a zero saving with zero wording', function () {
    $offer = publishedPublicOffer([], [
        'benchmark_price_minor' => 550,
        'saving_minor' => 0,
        'saving_percentage_bps' => 0,
    ]);

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentComparison.saving', ['minor' => 0, 'formatted' => '0.00 MAD/kg'])
            ->where('currentComparison.savingPercentage', '0.00%'));
});

it('labels a negative difference with signed data for the page to call it above benchmark', function () {
    $offer = publishedPublicOffer([], [
        'benchmark_price_minor' => 400,
        'saving_minor' => -150,
        'saving_percentage_bps' => -3750,
    ]);

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentComparison.saving', ['minor' => -150, 'formatted' => '-1.50 MAD/kg'])
            ->where('currentComparison.savingPercentage', '-37.50%'));
});

it('keeps the final price visible when the benchmark is fresh-unavailable', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('freshComparisonUnavailable', true)
            ->where('currentComparison', null)
            ->where('offer.finalPrice', ['minor' => 550, 'formatted' => '5.50 MAD/kg'])
            ->where('offer.farmerSharePercentage', '50.91%'));
});

it('returns the same not-found outcome for draft, withdrawn, and missing offers', function () {
    $draft = ProductOffer::factory()->create();
    $withdrawn = ProductOffer::factory()->withdrawn()->create();
    $missing = Str::uuid()->toString();

    foreach ([$draft->public_id, $withdrawn->public_id, $missing] as $publicId) {
        $this->get(route('offers.show', $publicId))->assertNotFound();
    }
});

it('applies the public throttle to the show route', function () {
    RateLimiter::shouldReceive('limiter')
        ->with('public-offers')
        ->andReturn(fn ($request) => Limit::perMinute(120)->by('test-ip'));

    RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);

    RateLimiter::shouldReceive('availableIn')->andReturn(60);

    $this->get(route('offers.show', '00000000-0000-0000-0000-000000000000'))->assertStatus(429);
});

it('shows a superseded offer with its replacement link within thirty days', function () {
    $this->freezeTime();

    $offer = publishedPublicOffer([
        'published_at' => now()->subDays(20),
        'superseded_at' => now()->subDays(5),
    ], [
        'observed_at' => now()->subDays(5)->subHour(),
        'published_at' => now()->subDays(5)->subHour()->subMinute(),
    ]);

    $replacement = ProductOffer::factory()->published()->create();
    $replacement->forceFill(['replaces_product_offer_id' => $offer->id])->save();

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offer.isSuperseded', true)
            ->where('offer.supersededAt', $offer->superseded_at->toIso8601String())
            ->where('offer.replacesPublicId', null)
            ->where('offer.replacementPublicId', (string) $replacement->public_id)
            ->where('freshComparisonUnavailable', true));
});

it('shows a superseded comparison as history and never as the current claim', function () {
    $offer = publishedPublicOffer();

    BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 700,
        'market_name' => 'Old market',
        'observed_at' => now()->subDays(6),
        'saving_minor' => 150,
        'saving_percentage_bps' => 2143,
        'published_at' => now()->subDays(6)->subMinute(),
        'superseded_at' => now()->subDays(5),
    ]);

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentComparison.isSuperseded', false)
            ->where('currentComparison.marketName', 'Casablanca traditional market')
            ->has('comparisonHistory', 1)
            ->where('comparisonHistory.0.isSuperseded', true)
            ->where('comparisonHistory.0.marketName', 'Old market')
            ->where('comparisonHistory.0.supersededAt', $offer->benchmarkComparisons()->where('market_name', 'Old market')->first()->superseded_at->toIso8601String()));
});

it('keeps a superseded offer public at exactly thirty days after supersession', function () {
    $this->freezeTime();

    $offer = publishedPublicOffer([
        'published_at' => now()->subDays(35),
        'superseded_at' => now()->subDays(30),
    ], [
        'observed_at' => now()->subDays(31),
        'published_at' => now()->subDays(31)->subMinute(),
    ]);

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offer.isSuperseded', true)
            ->where('freshComparisonUnavailable', true));
});

it('returns a public not-found immediately after thirty days of supersession', function () {
    $this->freezeTime();

    $offer = publishedPublicOffer([
        'published_at' => now()->subDays(35),
        'superseded_at' => now()->subDays(30)->subSecond(),
    ], [
        'observed_at' => now()->subDays(31),
        'published_at' => now()->subDays(31)->subMinute(),
    ]);

    $this->get(route('offers.show', $offer->public_id))->assertNotFound();
});

it('hides superseded comparison history after the thirty-day window', function () {
    $this->freezeTime();

    $offer = publishedPublicOffer();

    BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 700,
        'observed_at' => now()->subDays(6),
        'saving_minor' => 150,
        'saving_percentage_bps' => 2143,
        'published_at' => now()->subDays(6)->subMinute(),
        'superseded_at' => now()->subDays(5),
    ]);

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('comparisonHistory', 1));

    $this->travel(26)->days();

    $this->get(route('offers.show', $offer->public_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('comparisonHistory', [])
            ->where('freshComparisonUnavailable', true));
});

it('keeps authorized staff access after the public window ends', function () {
    $this->freezeTime();

    $offer = ProductOffer::factory()->create([
        'published_at' => now()->subDays(40),
        'superseded_at' => now()->subDays(31),
        'withdrawn_at' => null,
    ]);

    $this->get(route('offers.show', $offer->public_id))->assertNotFound();

    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('operator.offers.edit', $offer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/manage')
            ->where('offer.status', 'superseded'));
});
