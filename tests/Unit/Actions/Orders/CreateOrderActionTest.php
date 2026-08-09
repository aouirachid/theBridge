<?php

use App\Actions\Orders\CreateOrderAction;
use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\ProductOffer;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function orderableOfferWithSlot(array $offerOverrides = []): ProductOffer
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

function firstSlot(ProductOffer $offer): OfferDeliverySlot
{
    return $offer->deliverySlots()->oldest()->firstOrFail();
}

function orderPayload(ProductOffer $offer, array $overrides = []): array
{
    return array_merge([
        'submission_token' => Str::uuid()->toString(),
        'channel' => OrderChannel::B2c->value,
        'quantity_kg' => '5.00',
        'delivery_slot_public_id' => (string) firstSlot($offer)->public_id,
        'delivery_zone' => DeliveryZone::CasablancaCentre->value,
        'customer_name' => '  Amina Benali ',
        'phone' => '+212612345678',
        'email' => ' Amina@Example.com ',
        'business_name' => null,
        'delivery_address' => ' 12 Rue des Orangers, Casablanca ',
        'delivery_note' => null,
    ], $overrides);
}

function createOrder(ProductOffer $offer, array $overrides = [], ?CarbonInterface $now = null): array
{
    return app(CreateOrderAction::class)->execute($offer->public_id, orderPayload($offer, $overrides), $now);
}

it('creates a confirmed B2C order with snapshots and both transitions', function () {
    $this->freezeTime();

    $offer = orderableOfferWithSlot();
    $confirmation = createOrder($offer, ['quantity_kg' => '5.00'], now());

    expect($confirmation)->toHaveKeys([
        'reference',
        'channel',
        'crop',
        'quantityKg',
        'deliveryZone',
        'deliverySlot',
        'unitPrice',
        'total',
        'currency',
        'status',
        'confirmedAt',
        'maskedPhone',
        'eligibleForNextCycle',
    ]);

    $order = Order::findOrFail(Order::where('public_id', $confirmation['reference'])->value('id'));

    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->confirmed_at->eq(now()->startOfSecond()))->toBeTrue();
    expect($order->channel)->toBe(OrderChannel::B2c);
    expect($order->delivery_zone)->toBe(DeliveryZone::CasablancaCentre);
    expect($order->quantity_hundredths)->toBe(500);
    expect($order->unit_price_minor)->toBe(550);
    expect($order->total_minor)->toBe(2750);
    expect($order->currency)->toBe('MAD');
    expect($order->offer_public_id_snapshot)->toBe((string) $offer->public_id);
    expect($order->crop_snapshot)->toBe($offer->crop);
    expect($order->offer_delivery_slot_id)->toBe(firstSlot($offer)->id);
    expect($order->service_date->toDateString())->toBe(firstSlot($offer)->starts_at->setTimezone('Africa/Casablanca')->toDateString());
    expect($order->slot_starts_at->eq(firstSlot($offer)->starts_at))->toBeTrue();
    expect($order->slot_ends_at->eq(firstSlot($offer)->ends_at))->toBeTrue();

    $history = $order->transitions()->get()->all();
    expect(count($history))->toBe(2);
    expect($history[0]->from_status)->toBeNull();
    expect($history[0]->to_status)->toBe(OrderStatus::Pending);
    expect($history[1]->from_status)->toBe(OrderStatus::Pending);
    expect($history[1]->to_status)->toBe(OrderStatus::Confirmed);
});

it('rolls back the order and initial transition when confirmation history fails', function () {
    $offer = orderableOfferWithSlot();
    $createdTransitions = 0;

    Event::listen('eloquent.creating: '.OrderStatusTransition::class, function () use (&$createdTransitions): void {
        $createdTransitions++;

        if ($createdTransitions === 2) {
            throw new RuntimeException('Simulated confirmation-history failure.');
        }
    });

    expect(fn () => createOrder($offer))->toThrow(RuntimeException::class);

    expect(Order::count())->toBe(0);
    expect(OrderStatusTransition::count())->toBe(0);
});

it('stores encrypted contact fields and never the raw submission token', function () {
    $offer = orderableOfferWithSlot();
    $payload = orderPayload($offer);

    createOrder($offer, $payload);

    $row = Order::query()->firstOrFail();

    expect($row->getRawOriginal('customer_name'))->not->toBe(trim($payload['customer_name']));
    expect($row->getRawOriginal('phone'))->not->toBe($payload['phone']);
    expect($row->getRawOriginal('delivery_address'))->not->toBe(trim($payload['delivery_address']));

    $plaintext = collect($row->getAttributes())->toJson();

    expect($plaintext)->not->toContain($payload['submission_token']);
    expect($row->submission_hash)->toBe(hash('sha256', $payload['submission_token']));
});

it('returns the allowlisted confirmation shape with masked phone', function () {
    $offer = orderableOfferWithSlot();
    $confirmation = createOrder($offer, ['phone' => '+212612345678']);

    expect($confirmation['maskedPhone'])->toBe('********78');
    expect($confirmation['status'])->toBe('confirmed');
    expect($confirmation['currency'])->toBe('MAD');
    expect($confirmation['eligibleForNextCycle'])->toBeTrue();
    expect($confirmation['unitPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
    expect($confirmation['total'])->toBe(['minor' => 2750, 'formatted' => '27.50 MAD']);
    expect($confirmation['quantityKg'])->toBe('5.00');
    expect($confirmation['deliveryZone'])->toBe(['code' => 'casablanca_centre', 'label' => 'Casablanca Centre']);
    expect($confirmation['deliverySlot']['publicId'] ?? null)->toBeNull();

    $encoded = json_encode($confirmation);

    expect($encoded)->not->toContain('Amina');
    expect($encoded)->not->toContain('0612345678');
    expect($encoded)->not->toContain('customer_name');
    expect($encoded)->not->toContain('submission_token');
    expect($encoded)->not->toContain('submission_hash');
});

it('creates a B2B order with business fields and no address', function () {
    $offer = orderableOfferWithSlot();

    $confirmation = createOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => ' Atlas Coop ',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    $order = Order::findOrFail(Order::where('public_id', $confirmation['reference'])->value('id'));

    expect($order->channel)->toBe(OrderChannel::B2b);
    expect($order->business_name)->toBe('Atlas Coop');
    expect($order->delivery_address)->toBeNull();
    expect($confirmation['channel'])->toBe('b2b');
});

it('creates a 40 kg B2B order at 220.00 MAD with null B2C-only fields', function () {
    $offer = orderableOfferWithSlot();

    $confirmation = createOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_kg' => '40.00',
        'business_name' => ' Atlas Coop ',
        'email' => 'contact@atlas.example',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    expect($confirmation['quantityKg'])->toBe('40.00');
    expect($confirmation['unitPrice'])->toBe(['minor' => 550, 'formatted' => '5.50 MAD/kg']);
    expect($confirmation['total'])->toBe(['minor' => 22000, 'formatted' => '220.00 MAD']);
    expect($confirmation['maskedPhone'])->toBe('********78');

    $order = Order::findOrFail(Order::where('public_id', $confirmation['reference'])->value('id'));

    expect($order->channel)->toBe(OrderChannel::B2b);
    expect($order->quantity_hundredths)->toBe(4000);
    expect($order->unit_price_minor)->toBe(550);
    expect($order->total_minor)->toBe(22000);
    expect($order->business_name)->toBe('Atlas Coop');
    expect($order->email)->toBe('contact@atlas.example');
    expect($order->delivery_address)->toBeNull();
    expect($order->delivery_note)->toBeNull();
});

it('stores the B2B business and buyer fields encrypted', function () {
    $offer = orderableOfferWithSlot();

    createOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'email' => 'contact@atlas.example',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    $row = Order::query()->firstOrFail();

    expect($row->getRawOriginal('business_name'))->not->toBe('Atlas Coop');
    expect($row->getRawOriginal('email'))->not->toBe('contact@atlas.example');
    expect($row->getRawOriginal('customer_name'))->not->toBe('Amina Benali');
    expect($row->business_name)->toBe('Atlas Coop');
    expect($row->email)->toBe('contact@atlas.example');
});

it('is idempotent for an identical B2B retry', function () {
    $offer = orderableOfferWithSlot();
    $payload = orderPayload($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    $first = createOrder($offer, $payload);
    $second = createOrder($offer, $payload);

    expect($first['reference'])->toBe($second['reference']);
    expect(Order::count())->toBe(1);
    expect(OrderStatusTransition::count())->toBe(2);
});

it('applies the shared availability rules to a B2B confirmation', function () {
    $offer = orderableOfferWithSlot();
    $b2b = [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ];

    createOrder($offer, array_merge($b2b, ['quantity_kg' => '98.00']));

    expect(fn () => createOrder($offer, array_merge($b2b, ['quantity_kg' => '3.00'])))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::QUANTITY_UNAVAILABLE,
        );
});

it('is idempotent for an identical retry', function () {
    $offer = orderableOfferWithSlot();
    $payload = orderPayload($offer);

    $first = createOrder($offer, $payload);
    $second = createOrder($offer, $payload);

    expect($first['reference'])->toBe($second['reference']);
    expect($second['status'])->toBe('confirmed');
    expect(Order::count())->toBe(1);
    expect(OrderStatusTransition::count())->toBe(2);
});

it('rejects a reused token with different defining details', function () {
    $offer = orderableOfferWithSlot();
    $payload = orderPayload($offer);

    createOrder($offer, $payload);

    expect(fn () => createOrder($offer, [
        'submission_token' => $payload['submission_token'],
        'quantity_kg' => '6.00',
    ]))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::SUBMISSION_MISMATCH,
        );
});

it('rejects a superseded offer as not found', function () {
    $this->freezeTime();

    $offer = orderableOfferWithSlot([
        'published_at' => now()->subDays(20),
        'superseded_at' => now()->subDays(5),
    ]);
    $offer->forceFill(['replaces_product_offer_id' => null])->save();
    $replacement = ProductOffer::factory()->published()->create();
    $replacement->forceFill(['replaces_product_offer_id' => $offer->id])->save();

    expect(fn () => createOrder($offer, [], now()))
        ->toThrow(NotFoundHttpException::class);
});

it('rejects an offer whose availability window has ended as offer unavailable', function () {
    $this->freezeTime();

    $offer = orderableOfferWithSlot([
        'availability_ends_at' => now()->subDay(),
    ]);

    expect(fn () => createOrder($offer, [], now()))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::OFFER_UNAVAILABLE,
        );
});

it('rejects a delivery slot that has already started', function () {
    $this->freezeTime();

    $offer = orderableOfferWithSlot();
    $past = $offer->deliverySlots()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(2),
    ]);

    expect(fn () => createOrder($offer, [
        'delivery_slot_public_id' => (string) $past->public_id,
    ], now()))->toThrow(
        fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::SLOT_UNAVAILABLE,
    );
});

it('rejects a quantity above the exact remainder', function () {
    $offer = orderableOfferWithSlot();

    createOrder($offer, ['quantity_kg' => '98.00']);

    expect(fn () => createOrder($offer, ['quantity_kg' => '3.00']))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::QUANTITY_UNAVAILABLE,
        );
});

it('allows a quantity exactly equal to the remaining availability', function () {
    $offer = orderableOfferWithSlot();

    createOrder($offer, ['quantity_kg' => '98.00']);
    createOrder($offer, ['quantity_kg' => '2.00']);

    expect(Order::count())->toBe(2);
});

it('returns the existing confirmation for an identical retry even after the offer changes', function () {
    $offer = orderableOfferWithSlot();
    $payload = orderPayload($offer);

    $first = createOrder($offer, $payload);

    $offer->update(['withdrawn_at' => now()]);

    $second = createOrder($offer, $payload);

    expect($second['reference'])->toBe($first['reference']);
});
