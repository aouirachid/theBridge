<?php

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function storeOffer(array $offerOverrides = []): ProductOffer
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

function storeSlot(ProductOffer $offer): OfferDeliverySlot
{
    return $offer->deliverySlots()->oldest()->firstOrFail();
}

function storeRoute(string $publicId): string
{
    return "/offers/{$publicId}/orders";
}

function storeBody(ProductOffer $offer, array $overrides = []): array
{
    return array_merge([
        'submission_token' => Str::uuid()->toString(),
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
        'delivery_slot_public_id' => (string) storeSlot($offer)->public_id,
        'delivery_zone' => DeliveryZone::CasablancaCentre->value,
        'customer_name' => 'Amina Benali',
        'phone' => '+212612345678',
        'email' => 'amina@example.com',
        'business_name' => null,
        'delivery_address' => '12 Rue des Orangers, Casablanca',
        'delivery_note' => null,
    ], $overrides);
}

it('confirms a B2C order over the public endpoint', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer));

    $response->assertOk();
    $response->assertJson([
        'channel' => 'b2c',
        'crop' => 'Tomatoes',
        'quantityKg' => '5.00',
        'deliveryZone' => ['code' => 'casablanca_centre', 'label' => 'Casablanca Centre'],
        'unitPrice' => ['minor' => 550, 'formatted' => '5.50 MAD/kg'],
        'total' => ['minor' => 2750, 'formatted' => '27.50 MAD'],
        'currency' => 'MAD',
        'status' => 'confirmed',
        'maskedPhone' => '********78',
        'eligibleForNextCycle' => true,
    ]);
    $response->assertJsonStructure([
        'reference',
        'deliverySlot' => ['startsAt', 'endsAt', 'serviceDate'],
        'confirmedAt',
    ]);

    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->offer_public_id_snapshot)->toBe((string) $offer->public_id);
    expect($order->crop_snapshot)->toBe('Tomatoes');
    expect(OrderStatusTransition::count())->toBe(2);
});

it('ignores client-supplied price and status fields during confirmation', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'final_price_minor' => 1,
        'unit_price_minor' => 1,
        'total_minor' => 1,
        'status' => 'cancelled',
        'confirmed_at' => '2000-01-01T00:00:00+00:00',
    ]));

    $response->assertOk();
    $response->assertJsonPath('total.minor', 2750);

    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Confirmed);
});

it('validates the confirmation request fields', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), [
        'submission_token' => Str::uuid()->toString(),
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['delivery_slot_public_id', 'delivery_zone', 'customer_name', 'phone']);
});

it('prohibits B2B-only fields for a B2C order', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'business_name' => 'Atlas Coop',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_name']);
});

it('requires business_name for a B2B order and stores no address', function () {
    $offer = storeOffer();

    $missing = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => null,
    ]));
    $missing->assertStatus(422);
    $missing->assertJsonValidationErrors(['business_name']);

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'delivery_address' => '12 Rue des Orangers, Casablanca',
    ]));
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['delivery_address']);
});

it('confirms a 40 kg B2B order at 220.00 MAD and keeps business details out of the JSON', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '40.00',
        'business_name' => 'Atlas Coop',
        'email' => 'contact@atlas.example',
        'delivery_address' => null,
        'delivery_note' => null,
    ]));

    $response->assertOk();
    $response->assertJson([
        'channel' => 'b2b',
        'quantityKg' => '40.00',
        'unitPrice' => ['minor' => 550, 'formatted' => '5.50 MAD/kg'],
        'total' => ['minor' => 22000, 'formatted' => '220.00 MAD'],
        'status' => 'confirmed',
    ]);
    $response->assertJsonMissing(['business_name' => 'Atlas Coop']);

    $encoded = json_encode($response->json());

    expect($encoded)->not->toContain('Atlas');
    expect($encoded)->not->toContain('Amina');
    expect($encoded)->not->toContain('contact@atlas.example');
    expect($encoded)->not->toContain('delivery_address');
    expect($encoded)->not->toContain('delivery_note');

    $order = Order::query()->firstOrFail();

    expect($order->channel)->toBe(OrderChannel::B2b);
    expect($order->quantity_hundredths)->toBe(4000);
    expect($order->total_minor)->toBe(22000);
    expect($order->business_name)->toBe('Atlas Coop');
});

it('accepts an optional email for a B2B order', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'email' => null,
        'delivery_address' => null,
        'delivery_note' => null,
    ]));

    $response->assertOk();
    expect($response->json('channel'))->toBe('b2b');
    expect(Order::query()->firstOrFail()->email)->toBeNull();
});

it('ignores client-supplied price and status fields on a B2B confirmation', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '40.00',
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
        'final_price_minor' => 1,
        'unit_price_minor' => 1,
        'total_minor' => 1,
        'status' => 'cancelled',
    ]));

    $response->assertOk();
    $response->assertJsonPath('total.minor', 22000);
    $response->assertJsonPath('status', 'confirmed');
    $response->assertJsonPath('quantityKg', '40.00');
});

it('prohibits a B2C-only delivery note for a B2B order', function () {
    $offer = storeOffer();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'delivery_note' => 'Leave at the gate',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['delivery_note']);
});

it('stores encrypted contact fields and the derived hash only', function () {
    $offer = storeOffer();
    $body = storeBody($offer);

    $this->postJson(storeRoute($offer->public_id), $body)->assertOk();

    $row = Order::query()->firstOrFail();
    expect($row->getRawOriginal('customer_name'))->not->toBe('Amina Benali');
    expect($row->submission_hash)->toBe(hash('sha256', $body['submission_token']));
});

it('returns the same confirmation for an identical idempotent retry', function () {
    $offer = storeOffer();
    $body = storeBody($offer);

    $first = $this->postJson(storeRoute($offer->public_id), $body)->assertOk()->json();
    $second = $this->postJson(storeRoute($offer->public_id), $body)->assertOk()->json();

    expect($second['reference'])->toBe($first['reference']);
    expect(Order::count())->toBe(1);
});

it('returns 409 submission_mismatch when a token is reused with different details', function () {
    $offer = storeOffer();
    $body = storeBody($offer);

    $this->postJson(storeRoute($offer->public_id), $body)->assertOk();

    $response = $this->postJson(storeRoute($offer->public_id), array_merge($body, [
        'quantity_kg' => '6.00',
    ]));

    $response->assertStatus(409);
    $response->assertJson(['error' => ['code' => 'submission_mismatch']]);
});

it('returns 409 slot_unavailable for a past delivery slot', function () {
    $this->freezeTime();

    $offer = storeOffer();
    $past = $offer->deliverySlots()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(2),
    ]);

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'delivery_slot_public_id' => (string) $past->public_id,
    ]));

    $response->assertStatus(409);
    $response->assertJson(['error' => ['code' => 'slot_unavailable']]);
});

it('returns 409 quantity_unavailable when the remainder is exhausted', function () {
    $offer = storeOffer();

    $this->postJson(storeRoute($offer->public_id), storeBody($offer, ['quantity_kg' => '98.00']))->assertOk();

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer, ['quantity_kg' => '3.00']));

    $response->assertStatus(409);
    $response->assertJson(['error' => ['code' => 'quantity_unavailable']]);
});

it('limits public order confirmations to ten per minute', function () {
    $offer = storeOffer();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson(storeRoute($offer->public_id), storeBody($offer))->assertOk();
    }

    $response = $this->postJson(storeRoute($offer->public_id), storeBody($offer));

    $response->assertStatus(429);
});
