<?php

use App\Actions\ProductOffers\ShowPublicProductOfferAction;
use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function publicOffer(array $offerOverrides = [], array $comparisonOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()
        ->published()
        ->withStandardCosts()
        ->create($offerOverrides);

    BenchmarkComparison::factory()->create(array_merge([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 800,
        'market_name' => 'Casablanca traditional market',
        'source_type' => 'field_observation',
        'source_reference' => 'Phase 1 field observation',
        'observed_at' => now()->subHour(),
        'is_demo' => false,
        'saving_minor' => 250,
        'saving_percentage_bps' => 3125,
        'published_at' => now()->subHour()->subMinute(),
    ], $comparisonOverrides));

    return $offer;
}

function assertNoPrivateKeys(array $data): void
{
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

    foreach ($data as $key => $value) {
        expect($forbidden)->not->toContain($key);
        if (is_array($value)) {
            assertNoPrivateKeys($value);
        }
    }
}

it('returns the allowlisted public shape with no private fields', function () {
    $offer = publicOffer();

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    expect(array_keys($data))->toBe(['offer', 'currentComparison', 'freshComparisonUnavailable', 'comparisonHistory']);
    expect($data['freshComparisonUnavailable'])->toBeFalse();
    expect($data['comparisonHistory'])->toBe([]);

    assertNoPrivateKeys($data);
});

it('returns the exact tomato values for the public breakdown', function () {
    $offer = publicOffer();

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    $publicOffer = $data['offer'];
    expect($publicOffer['publicId'])->toBe((string) $offer->public_id);
    expect($publicOffer['crop'])->toBe('Tomatoes');
    expect($publicOffer['origin'])->toBe('Souss-Massa');
    expect($publicOffer['availableQuantityKg'])->toBe('100.00');
    expect($publicOffer['farmerPayment'])->toBe(['minor' => 280, 'formatted' => '2.80 MAD/kg']);
    expect($publicOffer['platformMargin'])->toBe(['minor' => 100, 'formatted' => '1.00 MAD/kg']);
    expect($publicOffer['finalPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
    expect($publicOffer['farmerSharePercentage'])->toBe('50.91%');
    expect($publicOffer['isSuperseded'])->toBeFalse();
    expect($publicOffer['supersededAt'])->toBeNull();
    expect($publicOffer['replacesPublicId'])->toBeNull();
    expect($publicOffer['replacementPublicId'])->toBeNull();

    $codes = array_column($publicOffer['costs'], 'code');
    expect($codes)->toBe(array_keys(OfferCostComponent::STANDARD_LABELS));
    expect($publicOffer['costs'])->each->toHaveKeys(['code', 'name', 'amount']);
    expect($publicOffer['costs'][0]['amount'])->toBe(['minor' => 30, 'formatted' => '0.30 MAD/kg']);

    $comparison = $data['currentComparison'];
    expect($comparison['marketName'])->toBe('Casablanca traditional market');
    expect($comparison['sourceType'])->toBe('field_observation');
    expect($comparison['sourceReference'])->toBe('Phase 1 field observation');
    expect($comparison['isDemo'])->toBeFalse();
    expect($comparison['benchmarkPrice'])->toBe(['minor' => 800, 'formatted' => '8.00 MAD/kg']);
    expect($comparison['saving'])->toBe(['minor' => 250, 'formatted' => '2.50 MAD/kg']);
    expect($comparison['savingPercentage'])->toBe('31.25%');
    expect($comparison['isSuperseded'])->toBeFalse();
    expect($comparison['supersededAt'])->toBeNull();
});

it('keeps a comparison visible exactly twenty-four hours after observation', function () {
    $this->freezeTime();

    $offer = publicOffer([], [
        'observed_at' => now()->subHours(24),
        'published_at' => now()->subHours(24)->subMinute(),
    ]);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now());

    expect($data['freshComparisonUnavailable'])->toBeFalse();
    expect($data['currentComparison'])->not->toBeNull();
});

it('hides the comparison immediately after twenty-four hours', function () {
    $this->freezeTime();

    $offer = publicOffer([], [
        'observed_at' => now()->subHours(24)->subSecond(),
        'published_at' => now()->subHours(24)->subSecond()->subMinute(),
    ]);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now());

    expect($data['freshComparisonUnavailable'])->toBeTrue();
    expect($data['currentComparison'])->toBeNull();
    expect($data['offer']['finalPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
});

it('keeps the offer visible with fresh comparison unavailable when no benchmark exists', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    expect($data['freshComparisonUnavailable'])->toBeTrue();
    expect($data['currentComparison'])->toBeNull();
    expect($data['offer']['finalPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
});

it('returns signed negative saving data when the price exceeds the benchmark', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create([
        'farmer_payment_minor' => 400,
        'platform_margin_minor' => 200,
        'final_price_minor' => 770,
        'farmer_share_bps' => 5195,
    ]);

    BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 700,
        'observed_at' => now()->subHour(),
        'saving_minor' => -70,
        'saving_percentage_bps' => -1000,
        'published_at' => now()->subHour()->subMinute(),
    ]);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    expect($data['currentComparison']['saving'])->toBe(['minor' => -70, 'formatted' => '-0.70 MAD/kg']);
    expect($data['currentComparison']['savingPercentage'])->toBe('-10.00%');
});

it('returns a zero saving for a price equal to the benchmark', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create([
        'final_price_minor' => 550,
    ]);

    BenchmarkComparison::factory()->create([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 550,
        'observed_at' => now()->subHour(),
        'saving_minor' => 0,
        'saving_percentage_bps' => 0,
        'published_at' => now()->subHour()->subMinute(),
    ]);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    expect($data['currentComparison']['saving'])->toBe(['minor' => 0, 'formatted' => '0.00 MAD/kg']);
    expect($data['currentComparison']['savingPercentage'])->toBe('0.00%');
});

it('bounds the superseded comparison history to thirty rows', function () {
    $offer = publicOffer();

    for ($i = 0; $i < 35; $i++) {
        BenchmarkComparison::factory()->create([
            'product_offer_id' => $offer->id,
            'benchmark_price_minor' => 600 + $i,
            'observed_at' => now()->subDays(5),
            'is_demo' => true,
            'saving_minor' => 50,
            'saving_percentage_bps' => 833,
            'published_at' => now()->subDays(5)->subMinute(),
            'superseded_at' => now()->subDays(4)->subMinutes($i),
        ]);
    }

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id);

    expect(count($data['comparisonHistory']))->toBe(30);
});

it('returns an identical not-found outcome for missing, draft, withdrawn, and stale superseded offers', function () {
    $this->freezeTime();

    $missing = Str::uuid()->toString();
    $draft = ProductOffer::factory()->create();
    $withdrawn = ProductOffer::factory()->withdrawn()->create();
    $staleSuperseded = ProductOffer::factory()->create([
        'published_at' => now()->subDays(40),
        'superseded_at' => now()->subDays(31),
        'withdrawn_at' => null,
    ]);

    $ids = [$missing, $draft->public_id, $withdrawn->public_id, $staleSuperseded->public_id];

    foreach ($ids as $publicId) {
        expect(fn () => app(ShowPublicProductOfferAction::class)->execute($publicId, now()))
            ->toThrow(NotFoundHttpException::class);
    }
});

it('keeps a superseded offer publicly visible within thirty days', function () {
    $this->freezeTime();

    $offer = publicOffer([
        'published_at' => now()->subDays(20),
        'superseded_at' => now()->subDays(5),
    ], [
        'observed_at' => now()->subDays(5)->subHour(),
        'published_at' => now()->subDays(5)->subHour()->subMinute(),
    ]);

    $replacement = ProductOffer::factory()->published()->create();
    $offer->forceFill(['replaces_product_offer_id' => null])->save();
    $replacement->forceFill(['replaces_product_offer_id' => $offer->id])->save();

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now());

    expect($data['offer']['isSuperseded'])->toBeTrue();
    expect($data['offer']['supersededAt'])->toBeString();
    expect($data['offer']['replacementPublicId'])->toBe((string) $replacement->public_id);
});

it('keeps a superseded offer public at exactly thirty days after supersession', function () {
    $this->freezeTime();

    $offer = publicOffer([
        'published_at' => now()->subDays(35),
        'superseded_at' => now()->subDays(30),
    ], [
        'observed_at' => now()->subDays(31),
        'published_at' => now()->subDays(31)->subMinute(),
    ]);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now());

    expect($data['offer']['isSuperseded'])->toBeTrue();
    expect($data['offer']['supersededAt'])->toBeString();
});

it('hides a superseded offer immediately after thirty days', function () {
    $this->freezeTime();

    $offer = publicOffer([
        'published_at' => now()->subDays(35),
        'superseded_at' => now()->subDays(30)->subSecond(),
    ], [
        'observed_at' => now()->subDays(31),
        'published_at' => now()->subDays(31)->subMinute(),
    ]);

    expect(fn () => app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now()))
        ->toThrow(NotFoundHttpException::class);
});

it('drops superseded comparison history immediately after the thirty-day window', function () {
    $this->freezeTime();

    $offer = publicOffer([], [
        'observed_at' => now()->subDays(6),
        'published_at' => now()->subDays(6)->subMinute(),
        'superseded_at' => now()->subDays(5),
    ]);

    $inside = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now());
    expect($inside['comparisonHistory'])->toHaveCount(1);

    $outside = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now()->addDays(26));
    expect($outside['comparisonHistory'])->toBe([]);
    expect($outside['freshComparisonUnavailable'])->toBeTrue();
});

it('captures a single clock value for the visibility decision', function () {
    $this->freezeTime();

    $offer = publicOffer([], [
        'observed_at' => now()->subHours(24),
        'published_at' => now()->subHours(24)->subMinute(),
    ]);

    $later = Carbon::now()->addMinutes(1);

    $data = app(ShowPublicProductOfferAction::class)->execute($offer->public_id, $later);

    expect($data['freshComparisonUnavailable'])->toBeTrue();
});
