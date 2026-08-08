<?php

use App\Actions\ProductOffers\ShowPublicProductOfferAction;
use App\Actions\ProductOffers\WithdrawProductOfferAction;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('withdraws a published offer one-way', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->create();

    $withdrawn = app(WithdrawProductOfferAction::class)->execute($user, $offer);

    $withdrawn->refresh();

    expect($withdrawn->withdrawn_at)->not->toBeNull();
    expect($withdrawn->withdrawn_at->startOfSecond()->equalTo(now()->startOfSecond()))->toBeTrue();
    expect($withdrawn->isWithdrawn())->toBeTrue();
    expect($withdrawn->status())->toBe('withdrawn');
});

it('is idempotent when the offer is already withdrawn', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->withdrawn()->create([
        'withdrawn_at' => now()->subDay(),
    ]);

    app(WithdrawProductOfferAction::class)->execute($user, $offer);

    expect($offer->refresh()->withdrawn_at->startOfSecond()->equalTo(now()->subDay()->startOfSecond()))->toBeTrue();
});

it('rejects withdrawing a draft', function () {
    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->create();

    expect(fn () => app(WithdrawProductOfferAction::class)->execute($user, $offer))
        ->toThrow(RuntimeException::class);
});

it('makes the offer immediately invisible publicly after withdrawal', function () {
    $this->freezeTime();

    $user = User::factory()->operator()->create();
    $offer = ProductOffer::factory()->published()->create();

    app(WithdrawProductOfferAction::class)->execute($user, $offer);

    expect(fn () => app(ShowPublicProductOfferAction::class)->execute($offer->public_id, now()))
        ->toThrow(NotFoundHttpException::class);
});
