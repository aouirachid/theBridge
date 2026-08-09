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

it('rejects an invalid operational phone without persisting an order', function (string $phone) {
    $offer = storeOffer();

    $this->postJson(storeRoute($offer->public_id), storeBody($offer, ['phone' => $phone]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(Order::count())->toBe(0);
})->with([
    'too short' => '06123',
    'unsupported prefix' => '0412345678',
    'too long' => '061234567890',
    'letters' => 'call-me',
    'hostile wrapper' => '<script>0612345678</script>',
]);

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

it('keeps public confirmations recursively free of private and internal data', function () {
    $offer = storeOffer();
    $body = storeBody($offer, [
        'customer_name' => 'Private Customer Canary',
        'phone' => '0611223344',
        'email' => 'private-canary@example.test',
        'delivery_address' => '99 Private Canary Street',
        'delivery_note' => 'Private delivery note canary',
    ]);

    $payload = $this->postJson(storeRoute($offer->public_id), $body)->assertOk()->json();
    $forbiddenKeys = [
        'id',
        'product_offer_id',
        'offer_delivery_slot_id',
        'submission_hash',
        'submission_token',
        'customer_name',
        'business_name',
        'phone',
        'email',
        'delivery_address',
        'delivery_note',
        'actor_user_id',
    ];
    $inspect = function (array $value) use (&$inspect, $forbiddenKeys): void {
        foreach ($value as $key => $child) {
            expect($forbiddenKeys)->not->toContain((string) $key);

            if (is_array($child)) {
                $inspect($child);
            }
        }
    };

    $inspect($payload);

    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('Private Customer Canary');
    expect($encoded)->not->toContain('0611223344');
    expect($encoded)->not->toContain('private-canary@example.test');
    expect($encoded)->not->toContain('99 Private Canary Street');
    expect($encoded)->not->toContain('Private delivery note canary');
    expect($encoded)->not->toContain($body['submission_token']);
});

it('confirms the combined 45 kg acceptance case and preserves both price snapshots', function () {
    $offer = storeOffer();

    $b2c = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'quantity_kg' => '5.00',
    ]))->assertOk()->json();

    $b2b = $this->postJson(storeRoute($offer->public_id), storeBody($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '40.00',
        'business_name' => 'Atlas Greengrocer',
        'delivery_address' => null,
        'delivery_note' => null,
    ]))->assertOk()->json();

    expect($b2c['total']['minor'])->toBe(2750);
    expect($b2b['total']['minor'])->toBe(22000);
    expect(Order::query()->sum('quantity_hundredths'))->toBe(4500);

    $offer->forceFill([
        'final_price_minor' => 650,
        'withdrawn_at' => now(),
    ])->save();

    expect(Order::query()->pluck('unit_price_minor')->all())->toBe([550, 550]);
    expect(Order::query()->pluck('total_minor')->all())->toBe([2750, 22000]);
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
