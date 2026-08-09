<?php

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a random public identity on creation', function () {
    $order = Order::factory()->create();

    expect($order->public_id)->not->toBeNull();
    expect(Order::query()->whereKeyNot($order->id)->where('public_id', $order->public_id)->exists())->toBeFalse();
});

it('encrypts contact attributes and returns plain values on the model', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Yassine Alaoui',
        'business_name' => null,
        'phone' => '+212600000000',
        'email' => 'yassine@example.test',
        'delivery_address' => '12 Rue des Oranges, Casablanca',
        'delivery_note' => 'Call on arrival',
    ]);

    $raw = DB::table('orders')->where('id', $order->id)->first();

    expect($raw->phone)->not->toBe('+212600000000');
    expect($raw->email)->not->toBe('yassine@example.test');
    expect($order->phone)->toBe('+212600000000');
    expect($order->email)->toBe('yassine@example.test');
    expect($order->customer_name)->toBe('Yassine Alaoui');
    expect($order->delivery_address)->toBe('12 Rue des Oranges, Casablanca');
    expect($order->delivery_note)->toBe('Call on arrival');
});

it('casts channel status and delivery zone to backed enums', function () {
    $order = Order::factory()->b2b()->create();

    expect($order->channel)->toBeInstanceOf(OrderChannel::class);
    expect($order->channel)->toBe(OrderChannel::B2b);
    expect($order->status)->toBeInstanceOf(OrderStatus::class);
    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->delivery_zone)->toBeInstanceOf(DeliveryZone::class);
    expect($order->delivery_zone)->toBe(DeliveryZone::CasablancaCentre);
    expect(DB::table('orders')->where('id', $order->id)->value('channel'))->toBe('b2b');
});

it('hides internal identifiers and encrypted attributes by default', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Yassine Alaoui',
        'phone' => '+212600000000',
        'email' => 'yassine@example.test',
        'delivery_address' => '12 Rue des Oranges, Casablanca',
    ]);

    $visible = $order->toArray();

    expect($visible)->not->toHaveKeys([
        'id',
        'submission_hash',
        'product_offer_id',
        'offer_delivery_slot_id',
        'customer_name',
        'business_name',
        'phone',
        'email',
        'delivery_address',
        'delivery_note',
    ]);
});

it('derives a masked phone fragment for confirmations', function () {
    $order = Order::factory()->create(['phone' => '+212612345678']);

    expect($order->maskedPhone())->toBe(str_repeat('*', 11).'78');
});

it('reports eligible for the next cycle only while confirmed', function () {
    expect(Order::factory()->create()->eligibleForNextCycle())->toBeTrue();
    expect(Order::factory()->cancelled()->create()->eligibleForNextCycle())->toBeFalse();
});

it('orders the append-only transition history oldest first', function () {
    $order = Order::factory()->create();
    $order->transitions()->create([
        'from_status' => null,
        'to_status' => OrderStatus::Pending,
    ]);
    $order->transitions()->create([
        'from_status' => OrderStatus::Pending,
        'to_status' => OrderStatus::Confirmed,
    ]);

    expect($order->transitions->pluck('to_status.value')->all())->toBe(['pending', 'confirmed']);
    expect($order->transitions->count())->toBe(2);
});

it('belongs to the source offer and selected slot', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();
    $slot = OfferDeliverySlot::factory()->create(['product_offer_id' => $offer->id]);
    $order = Order::factory()->forOffer($offer, $slot)->create();

    expect($order->offer->id)->toBe($offer->id);
    expect($order->deliverySlot->id)->toBe($slot->id);
    expect($offer->orders->pluck('id'))->toContain($order->id);
    expect($slot->orders->pluck('id'))->toContain($order->id);
});

it('stores immutable snapshots that match the selected slot', function () {
    $offer = ProductOffer::factory()->published()->withStandardCosts()->create();
    $slot = OfferDeliverySlot::factory()->create(['product_offer_id' => $offer->id]);
    $order = Order::factory()->forOffer($offer, $slot)->create([
        'quantity_hundredths' => 525,
        'unit_price_minor' => 550,
        'total_minor' => 2888,
    ]);

    $order->refresh();

    expect($order->offer_public_id_snapshot)->toBe((string) $offer->public_id);
    expect($order->crop_snapshot)->toBe($offer->crop);
    expect($order->slot_starts_at->equalTo($slot->starts_at))->toBeTrue();
    expect($order->slot_ends_at->equalTo($slot->ends_at))->toBeTrue();
    expect($order->service_date->toDateString())->toBe($slot->starts_at->copy()->setTimezone('Africa/Casablanca')->toDateString());
    expect($order->total_minor)->toBe(2888);
    expect($order->currency)->toBe('MAD');
});
