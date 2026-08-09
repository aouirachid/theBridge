<?php

use App\Actions\Orders\ReviewOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function orderableOffer(array $offerOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()
        ->published()
        ->withStandardCosts()
        ->create($offerOverrides);

    $offer->deliverySlots()->create([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
    ]);

    return $offer;
}

function reviewSlot(ProductOffer $offer): OfferDeliverySlot
{
    return $offer->deliverySlots()->oldest()->firstOrFail();
}

function reserve(ProductOffer $offer, int $quantityHundredths, ?OrderStatus $status = null): Order
{
    return Order::factory()->forOffer($offer, reviewSlot($offer), [
        'quantity_hundredths' => $quantityHundredths,
        'status' => $status ?? OrderStatus::Confirmed,
    ])->create();
}

function reviewOrder(ProductOffer $offer, array $overrides = []): array
{
    return app(ReviewOrderAction::class)->execute($offer->public_id, array_merge([
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
        'delivery_slot_public_id' => (string) reviewSlot($offer)->public_id,
    ], $overrides));
}

it('returns the allowlisted review shape for a B2C order', function () {
    $offer = orderableOffer();

    $review = reviewOrder($offer);

    expect($review)->toBe([
        'channel' => 'b2c',
        'crop' => 'Tomatoes',
        'quantityKg' => '5.00',
        'deliverySlot' => [
            'publicId' => (string) reviewSlot($offer)->public_id,
            'startsAt' => reviewSlot($offer)->starts_at->toIso8601String(),
            'endsAt' => reviewSlot($offer)->ends_at->toIso8601String(),
            'serviceDate' => reviewSlot($offer)->starts_at->setTimezone('Africa/Casablanca')->toDateString(),
        ],
        'unitPrice' => ['minor' => 550, 'formatted' => '5.50 MAD/kg'],
        'total' => ['minor' => 2750, 'formatted' => '27.50 MAD'],
    ]);
});

it('normalizes the quantity to exactly two decimals', function () {
    $offer = orderableOffer();

    $review = reviewOrder($offer, ['quantity_kg' => '5']);

    expect($review['quantityKg'])->toBe('5.00');
    expect($review['total'])->toBe(['minor' => 2750, 'formatted' => '27.50 MAD']);
});

it('rounds the one-time total half up', function () {
    $offer = orderableOffer();

    $review = reviewOrder($offer, ['quantity_kg' => '0.55']);

    expect($review['total'])->toBe(['minor' => 303, 'formatted' => '3.03 MAD']);
});

it('returns a B2B review with the same shape', function () {
    $offer = orderableOffer();

    $review = reviewOrder($offer, ['channel' => OrderChannel::B2b->value]);

    expect($review['channel'])->toBe('b2b');
    expect($review)->toHaveKeys(['crop', 'quantityKg', 'deliverySlot', 'unitPrice', 'total']);
});

it('returns a B2B review for 40 kg at the authoritative total', function () {
    $offer = orderableOffer();

    $review = reviewOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '40.00',
    ]);

    expect($review['channel'])->toBe('b2b');
    expect($review['quantityKg'])->toBe('40.00');
    expect($review['unitPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
    expect($review['total'])->toBe(['minor' => 22000, 'formatted' => '220.00 MAD']);
});

it('applies the shared availability rules to a B2B review', function () {
    $offer = orderableOffer();
    reserve($offer, 9800);

    expect(fn () => reviewOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '2.50',
    ]))->toThrow(
        fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::QUANTITY_UNAVAILABLE,
    );
});

it('treats missing, draft, withdrawn, and superseded offers as not found', function () {
    $this->freezeTime();

    $missing = Str::uuid()->toString();
    $draft = ProductOffer::factory()->create();
    $withdrawn = ProductOffer::factory()->withdrawn()->create();
    $superseded = ProductOffer::factory()->create([
        'published_at' => now()->subDays(20),
        'superseded_at' => now()->subDays(5),
    ]);
    $superseded->forceFill(['replaces_product_offer_id' => null])->save();
    $replacement = ProductOffer::factory()->published()->create();
    $replacement->forceFill(['replaces_product_offer_id' => $superseded->id])->save();

    foreach ([$missing, $draft->public_id, $withdrawn->public_id, $superseded->public_id] as $publicId) {
        expect(fn () => app(ReviewOrderAction::class)->execute($publicId, [
            'channel' => OrderChannel::B2c->value,
            'quantity_kg' => '5.00',
            'delivery_slot_public_id' => Str::uuid()->toString(),
        ]))->toThrow(NotFoundHttpException::class);
    }
});

it('rejects an offer whose availability window has ended as offer unavailable', function () {
    $this->freezeTime();

    $offer = orderableOffer(['availability_ends_at' => now()->subDay()]);

    expect(fn () => reviewOrder($offer, [], now()))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::OFFER_UNAVAILABLE,
        );
});

it('rejects a slot that does not belong to the offer', function () {
    $offer = orderableOffer();
    $other = orderableOffer();

    expect(fn () => reviewOrder($offer, [
        'delivery_slot_public_id' => (string) reviewSlot($other)->public_id,
    ]))->toThrow(
        fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::SLOT_UNAVAILABLE,
    );
});

it('rejects a delivery slot that has already started', function () {
    $this->freezeTime();

    $offer = orderableOffer();
    $past = $offer->deliverySlots()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(2),
    ]);

    expect(fn () => app(ReviewOrderAction::class)->execute($offer->public_id, [
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
        'delivery_slot_public_id' => (string) $past->public_id,
    ], now()))->toThrow(
        fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::SLOT_UNAVAILABLE,
    );
});

it('rejects a quantity above the remaining availability', function () {
    $offer = orderableOffer();
    reserve($offer, 9800);

    expect(fn () => reviewOrder($offer, ['quantity_kg' => '2.50']))->toThrow(
        fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::QUANTITY_UNAVAILABLE,
    );
});

it('allows a quantity exactly equal to the remaining availability', function () {
    $offer = orderableOffer();
    reserve($offer, 9800);

    $review = reviewOrder($offer, ['quantity_kg' => '2.00']);

    expect($review['quantityKg'])->toBe('2.00');
});

it('ignores cancelled orders when computing the remainder', function () {
    $offer = orderableOffer();
    reserve($offer, 9800, OrderStatus::Cancelled);

    $review = reviewOrder($offer, ['quantity_kg' => '3.00']);

    expect($review['quantityKg'])->toBe('3.00');
});

it('does not write any order or transition during a review', function () {
    $offer = orderableOffer();

    reviewOrder($offer);

    expect(Order::count())->toBe(0);
});
