<?php

use App\Actions\ProductOffers\PublishBenchmarkComparisonAction;
use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function refreshedPublishedOffer(array $candidateOverrides = []): array
{
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
        'superseded_at' => null,
    ]);

    $candidate = BenchmarkComparison::factory()->create(array_merge([
        'product_offer_id' => $offer->id,
        'benchmark_price_minor' => 900,
        'observed_at' => now()->subMinutes(5),
        'saving_minor' => null,
        'saving_percentage_bps' => null,
        'published_at' => null,
        'superseded_at' => null,
    ], $candidateOverrides));

    return [$offer, $current, $candidate];
}

it('publishes a reviewed recorded comparison with exact signed savings', function () {
    $operator = User::factory()->operator()->create();
    [$offer, $current, $candidate] = refreshedPublishedOffer();

    $published = app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    $published->refresh();

    expect($published->published_at)->not->toBeNull();
    expect($published->published_by_user_id)->toBe($operator->id);
    expect($published->saving_minor)->toBe(350);
    expect($published->saving_percentage_bps)->toBe(3889);
    expect($published->supersedes_comparison_id)->toBe($current->id);
    expect($published->isPublished())->toBeTrue();
});

it('leaves the published offer economics unchanged', function () {
    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer();
    $publishedAt = $offer->published_at;

    app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    $offer->refresh();

    expect($offer->published_at->equalTo($publishedAt))->toBeTrue();
    expect($offer->final_price_minor)->toBe(550);
    expect($offer->farmer_share_bps)->toBe(5091);
    expect($offer->farmer_payment_minor)->toBe(280);
    expect($offer->platform_margin_minor)->toBe(100);
    expect($offer->costs()->count())->toBe(4);
});

it('accepts a comparison observed exactly twenty-four hours ago', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer([
        'observed_at' => now()->subHours(24),
    ]);

    $published = app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    expect($published->refresh()->published_at)->not->toBeNull();
});

it('rejects a comparison observed after twenty-four hours', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer([
        'observed_at' => now()->subHours(24)->subSecond(),
    ]);

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate))
        ->toThrow(RuntimeException::class);
});

it('rejects a future comparison observation', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer([
        'observed_at' => now()->addMinutes(5),
    ]);

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate))
        ->toThrow(RuntimeException::class);
});

it('returns exact signed negative savings for a price above the benchmark', function () {
    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer([
        'benchmark_price_minor' => 400,
    ]);

    $published = app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    expect($published->refresh()->saving_minor)->toBe(-150);
    expect($published->saving_percentage_bps)->toBe(-3750);
});

it('rejects a comparison that belongs to another offer', function () {
    $operator = User::factory()->operator()->create();
    [$offer] = refreshedPublishedOffer();
    $otherOffer = ProductOffer::factory()->published()->create();
    $foreign = BenchmarkComparison::factory()->create(['product_offer_id' => $otherOffer->id]);

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $foreign))
        ->toThrow(RuntimeException::class);
});

it('rejects publishing a refresh for a withdrawn offer', function () {
    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer();
    $offer->forceFill(['withdrawn_at' => now()])->save();

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate))
        ->toThrow(RuntimeException::class);
});

it('rejects publishing a refresh for a superseded offer', function () {
    $operator = User::factory()->operator()->create();
    [$offer, , $candidate] = refreshedPublishedOffer();
    $offer->forceFill(['superseded_at' => now()])->save();

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate))
        ->toThrow(RuntimeException::class);
});

it('supersedes the prior current comparison once without rewriting its inputs', function () {
    $operator = User::factory()->operator()->create();
    [$offer, $current, $candidate] = refreshedPublishedOffer();

    app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    $current->refresh();

    expect($current->superseded_at)->not->toBeNull();
    expect($current->isSuperseded())->toBeTrue();
    expect($current->benchmark_price_minor)->toBe(800);
    expect($current->saving_minor)->toBe(250);
    expect($current->saving_percentage_bps)->toBe(3125);
    expect($current->published_at)->not->toBeNull();
    expect($current->supersedes_comparison_id)->toBeNull();
});

it('is idempotent when repeating publication of the same row', function () {
    $operator = User::factory()->operator()->create();
    [$offer, $current, $candidate] = refreshedPublishedOffer();

    $first = app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);
    $current->refresh();
    $supersededAt = $current->superseded_at;

    $second = app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate);

    $candidate->refresh();

    expect($second->id)->toBe($first->id);
    expect($candidate->saving_minor)->toBe(350);
    expect($candidate->published_at->equalTo($first->published_at))->toBeTrue();
    expect($current->refresh()->superseded_at->equalTo($supersededAt))->toBeTrue();
    expect(BenchmarkComparison::query()->where('supersedes_comparison_id', $current->id)->count())->toBe(1);
});

it('stays atomic when a refresh publication fails', function () {
    $this->freezeTime();

    $operator = User::factory()->operator()->create();
    [$offer, $current, $candidate] = refreshedPublishedOffer([
        'observed_at' => now()->subDays(2),
    ]);

    expect(fn () => app(PublishBenchmarkComparisonAction::class)->execute($operator, $offer, $candidate))
        ->toThrow(RuntimeException::class);

    $candidate->refresh();
    $current->refresh();

    expect($candidate->published_at)->toBeNull();
    expect($candidate->saving_minor)->toBeNull();
    expect($candidate->saving_percentage_bps)->toBeNull();
    expect($candidate->published_by_user_id)->toBeNull();
    expect($candidate->supersedes_comparison_id)->toBeNull();
    expect($current->superseded_at)->toBeNull();
    expect($current->saving_minor)->toBe(250);
    expect($offer->refresh()->final_price_minor)->toBe(550);
});
