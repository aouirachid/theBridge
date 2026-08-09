<?php

use App\Enums\OrderChannel;
use App\Models\Order;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishedOfferWithSlot(array $offerOverrides = []): ProductOffer
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

function reviewRoute(string $publicId): string
{
    return "/offers/{$publicId}/orders/review";
}

function reviewBody(ProductOffer $offer, array $overrides = []): array
{
    $slot = $offer->deliverySlots()->oldest()->firstOrFail();

    return array_merge([
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
        'delivery_slot_public_id' => (string) $slot->public_id,
    ], $overrides);
}

it('reviews a B2C order over the public endpoint', function () {
    $offer = publishedOfferWithSlot();

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer));

    $response->assertOk();
    $response->assertJson([
        'channel' => 'b2c',
        'crop' => 'Tomatoes',
        'quantityKg' => '5.00',
        'unitPrice' => ['minor' => 550, 'formatted' => '5.50 MAD/kg'],
        'total' => ['minor' => 2750, 'formatted' => '27.50 MAD'],
    ]);
    $response->assertJsonPath('deliverySlot.publicId', (string) $offer->deliverySlots()->oldest()->value('public_id'));
    $response->assertJsonStructure([
        'deliverySlot' => ['startsAt', 'endsAt', 'serviceDate'],
    ]);
});

it('ignores client-supplied price fields during review', function () {
    $offer = publishedOfferWithSlot();

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer, [
        'final_price_minor' => 1,
        'status' => 'confirmed',
        'total' => ['minor' => 1, 'formatted' => '0.01 MAD'],
    ]));

    $response->assertOk();
    $response->assertJsonPath('total.minor', 2750);
});

it('validates the review request fields', function () {
    $offer = publishedOfferWithSlot();

    $response = $this->postJson(reviewRoute($offer->public_id), [
        'quantity_kg' => '5.00',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['channel', 'delivery_slot_public_id']);
});

it('returns 404 with a generic message for a withdrawn offer', function () {
    $offer = publishedOfferWithSlot(['withdrawn_at' => now()]);

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer));

    $response->assertNotFound();
    $response->assertJsonPath('message', 'The offer could not be found.');
});

it('returns 409 slot_unavailable for a foreign delivery slot', function () {
    $offer = publishedOfferWithSlot();
    $other = publishedOfferWithSlot();

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer, [
        'delivery_slot_public_id' => (string) $other->deliverySlots()->oldest()->value('public_id'),
    ]));

    $response->assertStatus(409);
    $response->assertJson([
        'error' => ['code' => 'slot_unavailable', 'message' => 'This delivery slot is no longer available.'],
    ]);
});

it('returns 409 quantity_unavailable when the remainder is exhausted', function () {
    $offer = publishedOfferWithSlot();
    Order::factory()->forOffer($offer, $offer->deliverySlots()->oldest()->firstOrFail(), [
        'quantity_hundredths' => 9800,
    ])->create();

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer, ['quantity_kg' => '2.50']));

    $response->assertStatus(409);
    $response->assertJson(['error' => ['code' => 'quantity_unavailable']]);
});

it('does not write any order during a public review', function () {
    $offer = publishedOfferWithSlot();

    $this->postJson(reviewRoute($offer->public_id), reviewBody($offer))->assertOk();

    expect(Order::count())->toBe(0);
});

it('limits public order previews to thirty per minute', function () {
    $offer = publishedOfferWithSlot();

    for ($i = 0; $i < 30; $i++) {
        $this->postJson(reviewRoute($offer->public_id), reviewBody($offer))->assertOk();
    }

    $response = $this->postJson(reviewRoute($offer->public_id), reviewBody($offer));

    $response->assertStatus(429);
});
