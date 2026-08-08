<?php

use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->member = User::factory()->create();
});

function validStorePayload(array $overrides = []): array
{
    return array_merge([
        'crop' => 'Tomatoes',
        'origin' => 'Souss-Massa',
        'available_quantity_kg' => '100.00',
        'availability_starts_at' => now()->addDay()->startOfDay()->toDateTimeString(),
        'availability_ends_at' => now()->addDays(8)->startOfDay()->toDateTimeString(),
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

function draftWithStandardCosts(array $overrides = []): ProductOffer
{
    $offer = ProductOffer::factory()->create(array_merge([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
    ], $overrides));
    $offer->costs()->saveMany([
        OfferCostComponent::factory()->standard('collection', 30)->make(),
        OfferCostComponent::factory()->standard('quality_control', 20)->make(),
        OfferCostComponent::factory()->standard('hub_handling_storage', 30)->make(),
        OfferCostComponent::factory()->standard('delivery_allocation', 90)->make(),
    ]);

    return $offer;
}

it('redirects guests to login', function () {
    $this->get(route('operator.offers.index'))
        ->assertRedirect(route('login'));
});

it('forbids non-operator members', function () {
    $this->actingAs($this->member)
        ->get(route('operator.offers.index'))
        ->assertForbidden();
});

it('lists offers for an operator', function () {
    ProductOffer::factory()->count(2)->create();

    $this->actingAs($this->operator)
        ->get(route('operator.offers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/index')
            ->has('offers', 2));
});

it('shows the create form with standard cost labels', function () {
    $this->actingAs($this->operator)
        ->get(route('operator.offers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/manage')
            ->where('mode', 'create')
            ->has('standardCostLabels')
            ->where('can.update', false));
});

it('stores a draft offer and recalculates the snapshot', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload())
        ->assertRedirectToRoute('operator.offers.edit', ProductOffer::query()->sole())
        ->assertSessionHas('inertia.flash_data.toast');

    $offer = ProductOffer::query()->sole();

    expect($offer->final_price_minor)->toBe(550);
    expect($offer->farmer_share_bps)->toBe(5091);
    expect($offer->status())->toBe('draft');
});

it('returns nested validation errors for missing standard costs', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'standard_costs' => [
                'collection' => '0.30',
                'quality_control' => '0.20',
                'hub_handling_storage' => '0.30',
            ],
        ]))
        ->assertSessionHasErrors('standard_costs.delivery_allocation');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('stores only integer-centime values from decimal input', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'farmer_payment_per_kg' => '2.8',
            'platform_margin_per_kg' => '1.5',
        ]))
        ->assertRedirectToRoute('operator.offers.edit', ProductOffer::query()->sole());

    $offer = ProductOffer::query()->sole();

    expect($offer->farmer_payment_minor)->toBe(280);
    expect($offer->platform_margin_minor)->toBe(150);
});

it('rejects money with more than two fractional digits', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'platform_margin_per_kg' => '1.005',
        ]))
        ->assertSessionHasErrors('platform_margin_per_kg');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('shows the edit form for a draft with capabilities', function () {
    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->get(route('operator.offers.edit', $offer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/manage')
            ->where('mode', 'edit')
            ->where('offer.id', $offer->id)
            ->where('can.update', true));
});

it('updates a draft offer', function () {
    $offer = draftWithStandardCosts(['crop' => 'Tomatoes']);

    $this->actingAs($this->operator)
        ->patch(route('operator.offers.update', $offer), validStorePayload(['crop' => 'Carrots']))
        ->assertRedirectToRoute('operator.offers.edit', $offer);

    expect($offer->refresh()->crop)->toBe('Carrots');
    expect($offer->final_price_minor)->toBe(550);
});

it('forbids updating a published offer', function () {
    $offer = ProductOffer::factory()->published()->create();

    $this->actingAs($this->operator)
        ->patch(route('operator.offers.update', $offer), validStorePayload())
        ->assertForbidden();
});

it('records a benchmark for an offer', function () {
    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.benchmarks.store', $offer), [
            'benchmark_price_per_kg' => '8.00',
            'market_name' => 'Casablanca traditional market',
            'source_type' => 'field_observation',
            'source_reference' => 'Phase 1 demo observation',
            'observed_at' => now()->subHour()->toDateTimeString(),
            'is_demo' => true,
        ])
        ->assertRedirectToRoute('operator.offers.edit', $offer);

    $benchmark = BenchmarkComparison::query()->sole();

    expect($benchmark->benchmark_price_minor)->toBe(800);
    expect($benchmark->published_at)->toBeNull();
});

it('publishes an offer with a recorded benchmark', function () {
    $offer = draftWithStandardCosts();
    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.publications.store', $offer), [
            'benchmark_comparison_id' => $benchmark->id,
        ])
        ->assertRedirectToRoute('operator.offers.edit', $offer)
        ->assertSessionHas('inertia.flash_data.toast');

    expect($offer->refresh()->published_at)->not->toBeNull();
    expect($offer->final_price_minor)->toBe(550);
    expect($benchmark->refresh()->saving_minor)->toBe(250);
});

it('rejects publishing with a stale benchmark', function () {
    $offer = draftWithStandardCosts();
    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subDays(3),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.publications.store', $offer), [
            'benchmark_comparison_id' => $benchmark->id,
        ])
        ->assertSessionHasErrors('benchmark_comparison_id');

    expect($offer->refresh()->published_at)->toBeNull();
});

it('withdraws a published offer as an operator with a redirect and toast', function () {
    $offer = ProductOffer::factory()->published()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.withdrawals.store', $offer))
        ->assertRedirectToRoute('operator.offers.index')
        ->assertSessionHas('inertia.flash_data.toast');

    expect($offer->refresh()->withdrawn_at)->not->toBeNull();
});

it('forbids non-operator members from withdrawing an offer', function () {
    $offer = ProductOffer::factory()->published()->create();

    $this->actingAs($this->member)
        ->post(route('operator.offers.withdrawals.store', $offer))
        ->assertForbidden();

    expect($offer->refresh()->withdrawn_at)->toBeNull();
});

it('forbids withdrawing a draft offer', function () {
    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.withdrawals.store', $offer))
        ->assertForbidden();

    expect($offer->refresh()->withdrawn_at)->toBeNull();
});

it('repeats withdrawal safely without changing the transition', function () {
    $offer = ProductOffer::factory()->withdrawn()->create([
        'withdrawn_at' => now()->subDay(),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.withdrawals.store', $offer))
        ->assertRedirectToRoute('operator.offers.index');

    expect($offer->refresh()->withdrawn_at->startOfSecond()->equalTo(now()->subDay()->startOfSecond()))->toBeTrue();
});

it('returns a public not-found after withdrawal', function () {
    $offer = ProductOffer::factory()->published()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.withdrawals.store', $offer))
        ->assertRedirectToRoute('operator.offers.index');

    $this->get(route('offers.show', $offer->public_id))->assertNotFound();
});

it('applies the operator throttle to publish attempts', function () {
    RateLimiter::shouldReceive('limiter')
        ->with('operator-offers')
        ->andReturn(fn ($request) => Limit::perMinute(60)->by('test-operator'));

    RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);

    RateLimiter::shouldReceive('availableIn')->andReturn(60);

    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.publications.store', $offer), [])
        ->assertStatus(429);
});

function publishedSourceOffer(array $offerOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create(array_merge([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
    ], $offerOverrides));

    BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'observed_at' => now()->subHour(),
        'saving_minor' => 250,
        'saving_percentage_bps' => 3125,
        'published_at' => now()->subHour()->subMinute(),
    ]);

    return $offer;
}

it('clones a published offer into an editable replacement draft as an operator', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast');

    $draft = ProductOffer::query()->where('replaces_product_offer_id', $source->id)->sole();

    expect($draft->isDraft())->toBeTrue();
    expect($draft->crop)->toBe($source->crop);
    expect($draft->farmer_payment_minor)->toBe(280);
    expect($draft->costs()->count())->toBe(4);
});

it('forbids non-operator members from cloning a replacement', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->member)
        ->post(route('operator.offers.replacements.store', $source))
        ->assertForbidden();

    expect(ProductOffer::query()->count())->toBe(1);
});

it('opens the cloned replacement as an editable draft', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source));

    $draft = ProductOffer::query()->where('replaces_product_offer_id', $source->id)->sole();

    $this->actingAs($this->operator)
        ->get(route('operator.offers.edit', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/offers/manage')
            ->where('offer.id', $draft->id)
            ->where('offer.crop', $source->crop)
            ->where('can.update', true));
});

it('publishes a corrected replacement and supersedes its predecessor', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source));

    $draft = ProductOffer::query()->where('replaces_product_offer_id', $source->id)->sole();

    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $draft->id,
        'benchmark_price_minor' => 750,
        'observed_at' => now()->subHour(),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.publications.store', $draft), [
            'benchmark_comparison_id' => $benchmark->id,
        ])
        ->assertRedirectToRoute('operator.offers.edit', $draft)
        ->assertSessionHas('inertia.flash_data.toast');

    expect($draft->refresh()->published_at)->not->toBeNull();
    expect($source->refresh()->superseded_at)->not->toBeNull();
    expect($source->status())->toBe('superseded');
    expect($source->final_price_minor)->toBe(550);
});

it('links the replacement and predecessor after publication', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source));

    $draft = ProductOffer::query()->where('replaces_product_offer_id', $source->id)->sole();

    $benchmark = BenchmarkComparison::factory()->create([
        'product_offer_id' => $draft->id,
        'observed_at' => now()->subHour(),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.publications.store', $draft), [
            'benchmark_comparison_id' => $benchmark->id,
        ]);

    expect($draft->refresh()->replaces_product_offer_id)->toBe($source->id);
    expect($source->replacement()->first()->id)->toBe($draft->id);
});

it('prevents a duplicate replacement for the same offer', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source));

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source))
        ->assertForbidden();

    expect($source->replacement()->count())->toBe(1);
});
