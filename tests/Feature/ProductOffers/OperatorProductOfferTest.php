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
    $slotStartsAt = now()->addDays(2)->startOfDay()->addHours(2);

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
        'delivery_slots' => [
            ['starts_at' => $slotStartsAt->toDateTimeString(), 'ends_at' => $slotStartsAt->copy()->addHours(2)->toDateTimeString()],
            ['starts_at' => $slotStartsAt->copy()->addDay()->toDateTimeString(), 'ends_at' => $slotStartsAt->copy()->addDay()->addHours(2)->toDateTimeString()],
        ],
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
    $offer->deliverySlots()->create([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
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

it('stores a draft with its delivery slots', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload())
        ->assertRedirectToRoute('operator.offers.edit', ProductOffer::query()->sole());

    $slots = ProductOffer::query()->sole()->deliverySlots()->get();

    expect($slots)->toHaveCount(2);
    expect($slots->pluck('public_id'))->each->not->toBeNull();
    expect($slots->first()->starts_at->isFuture())->toBeTrue();
});

it('rejects duplicate delivery slots in the payload', function () {
    $startsAt = now()->addDays(2)->startOfDay()->addHours(2)->toDateTimeString();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'delivery_slots' => [
                ['starts_at' => $startsAt, 'ends_at' => now()->addDays(2)->startOfDay()->addHours(4)->toDateTimeString()],
                ['starts_at' => $startsAt, 'ends_at' => now()->addDays(2)->startOfDay()->addHours(4)->toDateTimeString()],
            ],
        ]))
        ->assertSessionHasErrors('delivery_slots.1.starts_at');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects more than fourteen delivery slots in the payload', function () {
    $slots = collect(range(1, 15))->map(fn (int $i) => [
        'starts_at' => now()->addDays(2)->startOfDay()->addHours($i * 2)->toDateTimeString(),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours($i * 2 + 1)->toDateTimeString(),
    ])->all();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload(['delivery_slots' => $slots]))
        ->assertSessionHasErrors('delivery_slots');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a delivery slot outside the offer window', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'delivery_slots' => [
                ['starts_at' => now()->addDays(9)->startOfDay()->toDateTimeString(), 'ends_at' => now()->addDays(9)->startOfDay()->addHours(2)->toDateTimeString()],
            ],
        ]))
        ->assertSessionHasErrors('delivery_slots.0.starts_at');

    expect(ProductOffer::query()->count())->toBe(0);
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

it('rejects an unknown standard cost key', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'standard_costs' => [
                'collection' => '0.30',
                'quality_control' => '0.20',
                'hub_handling_storage' => '0.30',
                'delivery_allocation' => '0.90',
                'packaging' => '0.10',
            ],
        ]))
        ->assertSessionHasErrors('standard_costs.packaging');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a non-array standard_costs value with a field error instead of crashing', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'standard_costs' => 'not-an-array',
        ]))
        ->assertSessionHasErrors('standard_costs');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a non-array custom_costs value with a field error instead of crashing', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'custom_costs' => 'not-an-array',
        ]))
        ->assertSessionHasErrors('custom_costs');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a non-empty body on the replacement route without changing data', function () {
    $source = publishedSourceOffer();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.replacements.store', $source), ['crop' => 'Hacked'])
        ->assertSessionHasErrors();

    expect($source->replacement()->count())->toBe(0);
});

it('rejects a non-empty body on the withdrawal route without changing data', function () {
    $offer = ProductOffer::factory()->published()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.withdrawals.store', $offer), ['crop' => 'Hacked'])
        ->assertSessionHasErrors();

    expect($offer->refresh()->withdrawn_at)->toBeNull();
});

it('rejects a non-empty body on the benchmark publication route without changing data', function () {
    $source = publishedSourceOffer();
    $candidate = BenchmarkComparison::factory()->create([
        'product_offer_id' => $source->id,
        'benchmark_price_minor' => 900,
        'observed_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($this->operator)
        ->post(route('operator.offers.benchmarks.publications.store', [$source, $candidate]), ['market_name' => 'Hacked'])
        ->assertSessionHasErrors();

    expect($candidate->refresh()->published_at)->toBeNull();
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

it('rejects a crop containing an email address', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'crop' => 'Tomatoes farmer@example.com',
        ]))
        ->assertSessionHasErrors('crop');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects an origin containing a phone number', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'origin' => 'Souss-Massa +212 661 234 567',
        ]))
        ->assertSessionHasErrors('origin');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a custom cost name containing an exact address', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'custom_costs' => [
                ['name' => 'Rue des Oliviers 45', 'amount_per_kg' => '0.50'],
            ],
        ]))
        ->assertSessionHasErrors('custom_costs.0.name');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects an origin containing a credential-bearing URL', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'origin' => 'Souss-Massa https://operator:secret@example.com',
        ]))
        ->assertSessionHasErrors('origin');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('rejects a benchmark source reference containing private contact details', function () {
    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.benchmarks.store', $offer), [
            'benchmark_price_per_kg' => '8.00',
            'market_name' => 'Casablanca traditional market',
            'source_type' => 'field_observation',
            'source_reference' => 'Call 06 12 34 56 78',
            'observed_at' => now()->subHour()->toDateTimeString(),
            'is_demo' => true,
        ])
        ->assertSessionHasErrors('source_reference');

    expect(BenchmarkComparison::query()->count())->toBe(0);
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

it('returns a field error for an invalid availability start datetime', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'availability_starts_at' => 'not-a-date',
        ]))
        ->assertSessionHasErrors('availability_starts_at');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('returns a field error for an invalid availability end datetime', function () {
    $this->actingAs($this->operator)
        ->post(route('operator.offers.store'), validStorePayload([
            'availability_ends_at' => 'not-a-date',
        ]))
        ->assertSessionHasErrors('availability_ends_at');

    expect(ProductOffer::query()->count())->toBe(0);
});

it('returns a field error for an invalid observed_at datetime', function () {
    $offer = ProductOffer::factory()->create();

    $this->actingAs($this->operator)
        ->post(route('operator.offers.benchmarks.store', $offer), [
            'benchmark_price_per_kg' => '8.00',
            'market_name' => 'Casablanca traditional market',
            'source_type' => 'field_observation',
            'source_reference' => 'Phase 1 demo observation',
            'observed_at' => 'not-a-date',
            'is_demo' => true,
        ])
        ->assertSessionHasErrors('observed_at');

    expect(BenchmarkComparison::query()->count())->toBe(0);
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

it('rejects publishing a draft without a future delivery slot', function () {
    $offer = ProductOffer::factory()->create([
        'farmer_payment_minor' => 280,
        'platform_margin_minor' => 100,
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
    $this->freezeTime();

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

    $offer->deliverySlots()->create([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
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
