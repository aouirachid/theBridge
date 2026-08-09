<?php

use App\Actions\Orders\ListOrdersAction;
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

function operatorOrderOffer(array $offerOverrides = [], array $slotOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()
        ->published()
        ->withStandardCosts()
        ->create($offerOverrides);

    $offer->deliverySlots()->create(array_merge([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
    ], $slotOverrides));

    return $offer;
}

function operatorSlot(ProductOffer $offer): OfferDeliverySlot
{
    return $offer->deliverySlots()->oldest()->firstOrFail();
}

function operatorOrder(ProductOffer $offer, array $overrides = []): Order
{
    return Order::factory()->forOffer($offer, operatorSlot($offer), $overrides)->create();
}

it('paginates twenty-five rows newest first', function () {
    $offer = operatorOrderOffer();
    $slot = operatorSlot($offer);

    Order::factory()->forOffer($offer, $slot)->count(26)->create();
    $newest = Order::query()->latest('id')->firstOrFail();
    $oldest = Order::query()->oldest('id')->firstOrFail();

    $result = app(ListOrdersAction::class)->execute();

    expect($result['orders']['total'])->toBe(26);
    expect($result['orders']['per_page'])->toBe(25);
    expect(count($result['orders']['data']))->toBe(25);
    expect($result['orders']['last_page'])->toBe(2);
    expect($result['orders']['data'][0]['reference'])->toBe($newest->public_id);

    $second = app(ListOrdersAction::class)->execute(['page' => 2]);

    expect(count($second['orders']['data']))->toBe(1);
    expect($second['orders']['data'][0]['reference'])->toBe($oldest->public_id);
});

it('filters the list by channel', function () {
    $offer = operatorOrderOffer();
    operatorOrder($offer, ['channel' => OrderChannel::B2c->value]);
    operatorOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    $result = app(ListOrdersAction::class)->execute(['channel' => OrderChannel::B2c->value]);

    expect($result['orders']['total'])->toBe(1);
    expect($result['orders']['data'][0]['channel'])->toBe('b2c');
    expect($result['filters']['channel'])->toBe('b2c');
});

it('filters the list by status', function () {
    $offer = operatorOrderOffer();
    operatorOrder($offer, ['status' => OrderStatus::Confirmed]);
    operatorOrder($offer, ['status' => OrderStatus::Cancelled]);

    $result = app(ListOrdersAction::class)->execute(['status' => OrderStatus::Cancelled->value]);

    expect($result['orders']['total'])->toBe(1);
    expect($result['orders']['data'][0]['status'])->toBe('cancelled');
    expect($result['filters']['status'])->toBe('cancelled');
});

it('filters the list by service date', function () {
    $offer = operatorOrderOffer();
    $otherOffer = operatorOrderOffer([], [
        'starts_at' => now()->addDays(3)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(3)->startOfDay()->addHours(4),
    ]);

    $first = operatorOrder($offer);
    operatorOrder($otherOffer);

    $result = app(ListOrdersAction::class)->execute(['service_date' => $first->service_date->toDateString()]);

    expect($result['orders']['total'])->toBe(1);
    expect($result['orders']['data'][0]['reference'])->toBe($first->public_id);
    expect($result['orders']['data'][0]['serviceDate'])->toBe($first->service_date->toDateString());
});

it('filters the list by offer', function () {
    $offerA = operatorOrderOffer();
    $offerB = operatorOrderOffer();

    $orderA = operatorOrder($offerA);
    operatorOrder($offerB);

    $result = app(ListOrdersAction::class)->execute(['offer' => (string) $offerA->public_id]);

    expect($result['orders']['total'])->toBe(1);
    expect($result['orders']['data'][0]['reference'])->toBe($orderA->public_id);
    expect($result['filters']['offer'])->toBe((string) $offerA->public_id);
});

it('filters the list by delivery zone', function () {
    $offer = operatorOrderOffer();
    operatorOrder($offer, ['delivery_zone' => DeliveryZone::CasablancaCentre->value]);
    operatorOrder($offer, ['delivery_zone' => DeliveryZone::CasablancaEast->value]);

    $result = app(ListOrdersAction::class)->execute(['delivery_zone' => DeliveryZone::CasablancaEast->value]);

    expect($result['orders']['total'])->toBe(1);
    expect($result['orders']['data'][0]['deliveryZone']['code'])->toBe('casablanca_east');
    expect($result['filters']['deliveryZone'])->toBe('casablanca_east');
});

it('preserves filter query parameters on pagination links', function () {
    $offer = operatorOrderOffer();
    $slot = operatorSlot($offer);

    Order::factory()->forOffer($offer, $slot)->count(30)->create();

    $result = app(ListOrdersAction::class)->execute(['channel' => OrderChannel::B2c->value, 'page' => 1]);

    expect($result['orders']['next_page_url'])->not->toBeNull();
    expect($result['orders']['next_page_url'])->toContain('channel=b2c');
    expect($result['orders']['next_page_url'])->toContain('page=2');

    $pageTwo = app(ListOrdersAction::class)->execute(['channel' => OrderChannel::B2c->value, 'page' => 2]);

    expect(count($pageTwo['orders']['data']))->toBe(5);
});

it('shows a five-kg B2C and a forty-kg B2B order as consolidation-eligible', function () {
    $offer = operatorOrderOffer();

    $b2c = operatorOrder($offer, ['quantity_hundredths' => 500]);
    $b2b = operatorOrder($offer, [
        'channel' => OrderChannel::B2b->value,
        'quantity_hundredths' => 4000,
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    $result = app(ListOrdersAction::class)->execute();

    $rows = collect($result['orders']['data']);
    $b2cRow = $rows->firstWhere('reference', $b2c->public_id);
    $b2bRow = $rows->firstWhere('reference', $b2b->public_id);

    expect($b2cRow['quantityKg'])->toBe('5.00');
    expect($b2cRow['unitPrice'])->toBe('5.50 MAD/kg');
    expect($b2cRow['total'])->toBe('27.50 MAD');
    expect($b2cRow['status'])->toBe('confirmed');
    expect($b2cRow['eligibleForNextCycle'])->toBeTrue();
    expect($b2cRow['serviceDate'])->toBe($b2bRow['serviceDate']);

    expect($b2bRow['quantityKg'])->toBe('40.00');
    expect($b2bRow['unitPrice'])->toBe('5.50 MAD/kg');
    expect($b2bRow['total'])->toBe('220.00 MAD');
    expect($b2bRow['status'])->toBe('confirmed');
    expect($b2bRow['eligibleForNextCycle'])->toBeTrue();
});

it('limits the offer filter options to the fifty most recent offers', function () {
    $offers = collect(range(1, 55))
        ->map(fn (): ProductOffer => operatorOrderOffer())
        ->all();
    $newest = $offers[count($offers) - 1];

    $result = app(ListOrdersAction::class)->execute();

    expect(count($result['filterOptions']['offers']))->toBe(50);
    expect($result['filterOptions']['offers'][0])->toMatchArray([
        'publicId' => (string) $newest->public_id,
    ]);
    expect($result['filterOptions']['offers'][0])->toHaveKeys(['publicId', 'crop']);
    expect($result['filterOptions']['offers'][49]['crop'])->toBe('Tomatoes');
});

it('keeps the query count constant as the order count grows', function () {
    $offer = operatorOrderOffer();
    $slot = operatorSlot($offer);

    Order::factory()->forOffer($offer, $slot)->count(5)->create();
    DB::enableQueryLog();
    DB::flushQueryLog();

    app(ListOrdersAction::class)->execute();
    $withFive = count(DB::getQueryLog());
    DB::flushQueryLog();

    Order::factory()->forOffer($offer, $slot)->count(21)->create();
    DB::flushQueryLog();

    app(ListOrdersAction::class)->execute();
    $withTwentySix = count(DB::getQueryLog());

    expect($withTwentySix)->toBeLessThanOrEqual($withFive);
});

it('never includes personal fields in the list output', function () {
    $offer = operatorOrderOffer();

    operatorOrder($offer, [
        'customer_name' => 'Yassine Privacy Test',
        'phone' => '+212600000001',
        'email' => 'yassine.privacy@example.com',
        'business_name' => 'Privacy Coop',
        'delivery_address' => '9 Rue Secrete, Casablanca',
        'delivery_note' => 'Leave with the concierge',
    ]);

    $result = app(ListOrdersAction::class)->execute();
    $encoded = json_encode($result);

    expect($encoded)->not->toContain('Yassine Privacy Test');
    expect($encoded)->not->toContain('+212600000001');
    expect($encoded)->not->toContain('yassine.privacy@example.com');
    expect($encoded)->not->toContain('Privacy Coop');
    expect($encoded)->not->toContain('9 Rue Secrete');
    expect($encoded)->not->toContain('Leave with the concierge');

    expect($encoded)->not->toContain('customer_name');
    expect($encoded)->not->toContain('business_name');
    expect($encoded)->not->toContain('delivery_address');
    expect($encoded)->not->toContain('delivery_note');
    expect($encoded)->not->toContain('submission_hash');
    expect($encoded)->not->toContain('product_offer_id');

    $row = $result['orders']['data'][0];

    expect(array_keys($row))->toBe([
        'reference',
        'channel',
        'crop',
        'quantityKg',
        'unitPrice',
        'total',
        'deliveryZone',
        'serviceDate',
        'status',
        'eligibleForNextCycle',
        'confirmedAt',
    ]);
});

it('returns camelCase filters and bounded filter options', function () {
    $offer = operatorOrderOffer();
    operatorOrder($offer);

    $result = app(ListOrdersAction::class)->execute();

    expect($result['filters'])->toBe([
        'channel' => null,
        'status' => null,
        'serviceDate' => null,
        'offer' => null,
        'deliveryZone' => null,
    ]);

    expect($result['filterOptions']['channels'])->toBe([
        ['value' => 'b2c', 'label' => 'Individual'],
        ['value' => 'b2b', 'label' => 'Business'],
    ]);

    expect($result['filterOptions']['statuses'])->toBe([
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'confirmed', 'label' => 'Confirmed'],
        ['value' => 'grouped', 'label' => 'Grouped'],
        ['value' => 'allocated', 'label' => 'Allocated'],
        ['value' => 'dispatched', 'label' => 'Dispatched'],
        ['value' => 'delivered', 'label' => 'Delivered'],
        ['value' => 'cancelled', 'label' => 'Cancelled'],
    ]);

    expect($result['filterOptions']['deliveryZones'])->toBe([
        ['value' => 'casablanca_centre', 'label' => 'Casablanca Centre'],
        ['value' => 'casablanca_east', 'label' => 'Casablanca East'],
        ['value' => 'casablanca_west', 'label' => 'Casablanca West'],
    ]);
});
