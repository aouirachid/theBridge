<?php

use App\Actions\ProductOffers\ListProductOffersAction;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the latest fifty offers first', function () {
    ProductOffer::factory()->count(55)->create();

    $summaries = app(ListProductOffersAction::class)->execute();

    expect($summaries)->toHaveCount(50);

    $ids = collect($summaries)->pluck('id')->all();
    $latest = ProductOffer::query()->orderByDesc('id')->limit(50)->pluck('id')->all();

    expect($ids)->toBe($latest);
});

it('returns the expected summary fields without actor details', function () {
    ProductOffer::factory()->create();

    $summary = app(ListProductOffersAction::class)->execute()[0];

    expect($summary)->toHaveKeys([
        'id',
        'publicId',
        'crop',
        'origin',
        'status',
        'finalPrice',
        'publishedAt',
        'canEdit',
        'canPublish',
        'canReplace',
        'publicUrlAvailable',
    ]);

    expect($summary)->not->toHaveKeys([
        'created_by_user_id',
        'creator',
        'email',
        'phone',
        'exact_address',
        'normalized_name',
    ]);
});

it('labels lifecycle status from timestamps', function () {
    ProductOffer::factory()->create();
    ProductOffer::factory()->published()->create();
    ProductOffer::factory()->superseded()->create();
    ProductOffer::factory()->withdrawn()->create();

    $statuses = collect(app(ListProductOffersAction::class)->execute())
        ->pluck('status')
        ->sort()
        ->values()
        ->all();

    expect($statuses)->toBe(['draft', 'published', 'superseded', 'withdrawn']);
});

it('reports capabilities and public url availability per status', function () {
    ProductOffer::factory()->create();
    ProductOffer::factory()->published()->create();
    ProductOffer::factory()->withdrawn()->create();

    $summaries = collect(app(ListProductOffersAction::class)->execute())
        ->keyBy('status');

    expect($summaries['draft'])->toMatchArray([
        'canEdit' => true,
        'canPublish' => true,
        'canReplace' => false,
        'publicUrlAvailable' => false,
    ]);

    expect($summaries['published'])->toMatchArray([
        'canEdit' => false,
        'canPublish' => false,
        'canReplace' => true,
        'publicUrlAvailable' => true,
    ]);

    expect($summaries['withdrawn'])->toMatchArray([
        'canEdit' => false,
        'canPublish' => false,
        'canReplace' => false,
        'publicUrlAvailable' => false,
    ]);
});

it('formats the stored final price as money', function () {
    ProductOffer::factory()->create(['final_price_minor' => 550]);

    $summary = app(ListProductOffersAction::class)->execute()[0];

    expect($summary['finalPrice'])->toBe('5.50 MAD/kg');
});

it('does not load actor relationships', function () {
    $user = User::factory()->create();
    ProductOffer::factory()->create(['created_by_user_id' => $user->id]);

    $summary = app(ListProductOffersAction::class)->execute()[0];

    expect($summary['publicId'])->toBeString();
    expect(ProductOffer::query()->with('creator')->count())->toBe(1);
});
