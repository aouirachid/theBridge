<?php

use App\Actions\ProductOffers\RecordBenchmarkComparisonAction;
use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function recordBenchmarkInput(array $overrides = []): array
{
    return array_replace_recursive([
        'benchmark_price_per_kg' => '8.00',
        'market_name' => 'Casablanca traditional market',
        'source_type' => 'field_observation',
        'source_reference' => 'Phase 1 demo observation',
        'observed_at' => now()->subHour()->toDateTimeString(),
        'is_demo' => true,
    ], $overrides);
}

it('records a benchmark without publishing it', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    $comparison = app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput());

    expect($comparison->benchmark_price_minor)->toBe(800);
    expect($comparison->recorded_by_user_id)->toBe($user->id);
    expect($comparison->published_at)->toBeNull();
    expect($comparison->is_demo)->toBeTrue();
    expect($comparison->source_type)->toBe('field_observation');
    expect($comparison->observed_at->isBefore(now()))->toBeTrue();
    expect($comparison->product_offer_id)->toBe($offer->id);
    expect($comparison->isRecorded())->toBeTrue();
    expect($comparison->isPublished())->toBeFalse();
});

it('stores attribution and demo flag but never touches public comparison state', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    $comparison = app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'is_demo' => false,
    ]));

    expect($comparison->is_demo)->toBeFalse();
    expect(BenchmarkComparison::query()->whereNotNull('published_at')->count())->toBe(0);
    expect($comparison->product_offer_id)->toBe($offer->id);
});

it('rejects a zero-value benchmark', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'benchmark_price_per_kg' => '0.00',
    ])))->toThrow(ValidationException::class);
});

it('rejects an invalid source type', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'source_type' => 'internet_rumor',
    ])))->toThrow(ValidationException::class);
});

it('rejects a negative benchmark price', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'benchmark_price_per_kg' => '-1.00',
    ])))->toThrow(ValidationException::class);
});

it('rejects an empty market name', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'market_name' => '',
    ])))->toThrow(ValidationException::class);
});

it('rejects an observed_at that is not a parseable date', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'observed_at' => 'not-a-date',
    ])))->toThrow(ValidationException::class);
});

it('rejects a market name containing an email address', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'market_name' => 'Market trader@example.com',
    ])))->toThrow(ValidationException::class);
});

it('rejects a market name containing a phone number', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'market_name' => 'Market +212 661 234 567',
    ])))->toThrow(ValidationException::class);
});

it('rejects a source reference containing an exact address', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'source_reference' => 'Lot 12, Rue des Oliviers 45, Agadir',
    ])))->toThrow(ValidationException::class);
});

it('rejects a source reference containing credentials in a URL', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
        'source_type' => 'url',
        'source_reference' => 'https://user:password@example.com/market',
    ])))->toThrow(ValidationException::class);
});

it('persists nothing when validation fails', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    try {
        app(RecordBenchmarkComparisonAction::class)->execute($user, $offer, recordBenchmarkInput([
            'market_name' => '',
        ]));
    } catch (ValidationException) {
    }

    expect(BenchmarkComparison::query()->count())->toBe(0);
});
